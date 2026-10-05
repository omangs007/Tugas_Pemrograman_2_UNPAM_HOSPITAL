<?php

declare(strict_types=1);

namespace App\Services\Doctors;

use App\Enums\DoctorStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaService;
use App\Support\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis dokter (F2-R20, 09 I-5/I-23): buat, ubah, spesialisasi, status, arsip, dan foto,
 * masing-masing dalam DB::transaction dengan audit eksplisit (Doctor tidak diobservasi AuditObserver, F2-R19).
 */
final class DoctorService
{
    public const MAX_SPECIALTIES = 5;

    /** Atribut profil yang boleh diisi dari input; slug, status, dan archived_at dikelola sistem. */
    private const PROFILE_ATTRIBUTES = [
        'clinic_id', 'name', 'title_prefix', 'title_suffix', 'gender', 'short_bio', 'bio',
        'sort_order', 'meta_title', 'meta_description',
    ];

    /** Kolom nullable: string kosong disimpan sebagai NULL. */
    private const NULLABLE_ATTRIBUTES = ['title_prefix', 'title_suffix', 'short_bio', 'bio', 'meta_title', 'meta_description'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MediaService $media,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor
    {
        [$specialtyIds, $primaryId] = $this->requireSpecialtyInput($data);
        $this->validatedSpecialtyIds($specialtyIds, $primaryId);

        return DB::transaction(function () use ($data, $actor, $photo, $photoAlt, $specialtyIds, $primaryId): Doctor {
            $doctor = new Doctor($this->profileAttributes($data));
            $doctor->status = DoctorStatus::Active;
            $doctor->save();

            $this->syncSpecialties($doctor, $specialtyIds, $primaryId);

            $sorted = $this->validatedSpecialtyIds($specialtyIds, $primaryId);
            sort($sorted);

            $this->audit->log(
                'doctor.created',
                $doctor,
                null,
                [
                    'name' => $doctor->name,
                    'clinic_id' => $doctor->clinic_id,
                    'status' => DoctorStatus::Active->value,
                    'specialty_ids' => $sorted,
                ],
                $this->describe('created', $doctor),
                $actor,
            );

            if ($photo !== null) {
                $this->media->store($photo, 'photo', $doctor, ['alt_text' => $photoAlt], $actor->user());
            }

            return $doctor;
        });
    }

    /**
     * Ganti seluruh spesialisasi dokter dengan tepat satu yang utama (09 I-5).
     *
     * @param  array<int, int|string>  $specialtyIds
     */
    public function syncSpecialties(Doctor $doctor, array $specialtyIds, int $primaryId): void
    {
        $ids = $this->validatedSpecialtyIds($specialtyIds, $primaryId);

        DB::transaction(function () use ($doctor, $ids, $primaryId): void {
            $payload = [];

            foreach ($ids as $id) {
                $payload[$id] = ['is_primary' => $id === $primaryId];
            }

            $doctor->specialties()->sync($payload);
        });

        $doctor->unsetRelation('specialties');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Doctor $doctor, array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor
    {
        $touchesSpecialties = array_key_exists('specialty_ids', $data) || array_key_exists('primary_specialty_id', $data);

        if ($touchesSpecialties && ! (array_key_exists('specialty_ids', $data) && array_key_exists('primary_specialty_id', $data))) {
            throw new InvalidArgumentException('specialty_ids dan primary_specialty_id harus dikirim bersama.');
        }

        $specialtyInput = $touchesSpecialties ? $this->requireSpecialtyInput($data) : null;

        if ($specialtyInput !== null) {
            $this->validatedSpecialtyIds($specialtyInput[0], $specialtyInput[1]);
        }

        return DB::transaction(function () use ($doctor, $data, $actor, $photo, $photoAlt, $specialtyInput): Doctor {
            $this->lock($doctor);

            $doctor->fill($this->profileAttributes($data));

            $new = $doctor->getDirty();
            $old = Arr::only($doctor->getRawOriginal(), array_keys($new));

            if ($new !== []) {
                $doctor->save();
            }

            if ($specialtyInput !== null) {
                [$ids, $primaryId] = $specialtyInput;
                $before = $this->currentSpecialties($doctor);

                $this->syncSpecialties($doctor, $ids, $primaryId);

                $after = $this->validatedSpecialtyIds($ids, $primaryId);
                sort($after);

                if ($before['ids'] !== $after) {
                    $old['specialty_ids'] = $before['ids'];
                    $new['specialty_ids'] = $after;
                }

                if ($before['primary'] !== $primaryId) {
                    $old['primary_specialty_id'] = $before['primary'];
                    $new['primary_specialty_id'] = $primaryId;
                }
            }

            if ($photo === null && $photoAlt !== null) {
                $existing = $doctor->media()->where('collection', 'photo')->first();

                if ($existing !== null && $existing->alt_text !== $photoAlt) {
                    $old['photo_alt'] = $existing->alt_text;
                    $new['photo_alt'] = $photoAlt;

                    $this->media->updateMeta($existing, $photoAlt, $existing->caption);
                }
            }

            if ($new !== []) {
                $this->audit->log('doctor.updated', $doctor, $old, $new, $this->describe('updated', $doctor), $actor);
            }

            if ($photo !== null) {
                $this->media->replace($doctor, 'photo', $photo, ['alt_text' => $photoAlt], $actor->user());
            }

            return $doctor;
        });
    }

    /**
     * Ganti foto dokter (koleksi tunggal `photo`). Berkas lama dihapus setelah transaksi commit.
     */
    public function replacePhoto(Doctor $doctor, UploadedFile $photo, string $alt, Actor $actor): Media
    {
        return DB::transaction(fn (): Media => $this->media->replace($doctor, 'photo', $photo, ['alt_text' => $alt], $actor->user()));
    }

    /**
     * Aktifkan/nonaktifkan dokter, atau pulihkan dokter arsip menjadi nonaktif (F2-R7).
     * Pengaruh ke sesi/appointment (00 F.4) baru ada di fase 3.
     */
    public function changeStatus(Doctor $doctor, DoctorStatus $status, ?string $reason, Actor $actor): Doctor
    {
        if ($status === DoctorStatus::Archived) {
            throw new InvalidArgumentException('Gunakan archive() untuk mengarsipkan dokter.');
        }

        $reason = $this->nullableTrim($reason);

        return DB::transaction(function () use ($doctor, $status, $reason, $actor): Doctor {
            $this->lock($doctor);

            $current = $doctor->status;

            if ($current === DoctorStatus::Archived && $status !== DoctorStatus::Inactive) {
                throw ValidationException::withMessages(['status' => 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.']);
            }

            if ($current === $status) {
                return $doctor;
            }

            $old = ['status' => $current->value];
            $new = ['status' => $status->value];

            if ($current === DoctorStatus::Archived) {
                $old['archived_at'] = $doctor->archived_at;
                $new['archived_at'] = null;
                $doctor->archived_at = null;
            }

            $doctor->status = $status;
            $doctor->save();

            if ($reason !== null) {
                $new['reason'] = $reason;
            }

            $verb = match (true) {
                $current === DoctorStatus::Archived => 'dipulihkan dari arsip',
                $status === DoctorStatus::Inactive => 'dinonaktifkan',
                default => 'diaktifkan',
            };

            $this->audit->log('doctor.status_changed', $doctor, $old, $new, "Dokter \"{$doctor->displayName}\" {$verb}.", $actor);

            return $doctor;
        });
    }

    /**
     * Arsipkan dokter (BR-03/BR-06). Ditolak bila masih ada pendaftaran aktif pada sesi mendatang (00 F.4);
     * aturan ini berlaku juga untuk Super Admin karena dijaga di sini, bukan di Policy.
     */
    public function archive(Doctor $doctor, ?string $reason, Actor $actor): Doctor
    {
        $reason = $this->nullableTrim($reason);

        return DB::transaction(function () use ($doctor, $reason, $actor): Doctor {
            $this->lock($doctor);

            if ($doctor->status === DoctorStatus::Archived) {
                throw ValidationException::withMessages(['status' => 'Dokter sudah diarsipkan.']);
            }

            $blocked = $this->archiveBlockedReason($doctor);

            if ($blocked !== null) {
                throw ValidationException::withMessages(['status' => $blocked]);
            }

            $old = ['status' => $doctor->status->value, 'archived_at' => null];

            $doctor->status = DoctorStatus::Archived;
            $doctor->archived_at = now();
            $doctor->save();

            $new = ['status' => DoctorStatus::Archived->value, 'archived_at' => $doctor->archived_at];

            if ($reason !== null) {
                $new['reason'] = $reason;
            }

            $this->audit->log('doctor.archived', $doctor, $old, $new, "Dokter \"{$doctor->displayName}\" diarsipkan.", $actor);

            return $doctor;
        });
    }

    /**
     * Alasan arsip ditolak (atau null bila boleh). Dipakai ArchiveDoctorRequest dan archive().
     */
    public function archiveBlockedReason(Doctor $doctor): ?string
    {
        $count = $this->futureActiveAppointmentsCount($doctor);

        return $count > 0 ? self::blockedMessage($count) : null;
    }

    /**
     * Jumlah appointment aktif dokter pada sesi yang belum berakhir (F2-R8, F3-R15). Definisi "aktif
     * mendatang" hanya ada di Appointment::scopeUpcomingActive(). Tetap protected agar tes dapat
     * menimpanya lewat subkelas (DoctorService bersifat final, sehingga lewat ArchiveDoctorRequest).
     */
    protected function futureActiveAppointmentsCount(Doctor $doctor): int
    {
        return Appointment::query()
            ->where('appointments.doctor_id', $doctor->getKey())
            ->upcomingActive()
            ->count();
    }

    private static function blockedMessage(int $count): string
    {
        return "Dokter masih memiliki {$count} pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.";
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Baca ulang baris dengan kunci tulis dan perbarui instance pemanggil, agar instance basi tidak menimpa
     * perubahan lain (status, arsip). Dipanggil di dalam transaksi.
     */
    private function lock(Doctor $doctor): void
    {
        $fresh = Doctor::query()->whereKey($doctor->getKey())->lockForUpdate()->firstOrFail();

        $doctor->setRawAttributes($fresh->getRawOriginal(), true);
    }

    /**
     * @return array{ids: list<int>, primary: ?int}
     */
    private function currentSpecialties(Doctor $doctor): array
    {
        $rows = DB::table('doctor_specialties')->where('doctor_id', $doctor->getKey())->get(['specialty_id', 'is_primary']);
        $ids = $rows->pluck('specialty_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $primary = $rows->first(fn ($row): bool => (bool) $row->is_primary);

        return ['ids' => $ids, 'primary' => $primary === null ? null : (int) $primary->specialty_id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileAttributes(array $data): array
    {
        $attributes = Arr::only($data, self::PROFILE_ATTRIBUTES);

        foreach ($attributes as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' && in_array($key, self::NULLABLE_ATTRIBUTES, true)) {
                $value = null;
            }

            $attributes[$key] = $value;
        }

        // sort_order kosong = biarkan nilai bawaan kolom (0) atau nilai yang sudah ada.
        if (array_key_exists('sort_order', $attributes) && ($attributes['sort_order'] === null || $attributes['sort_order'] === '')) {
            unset($attributes['sort_order']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<int>, 1: int}
     */
    private function requireSpecialtyInput(array $data): array
    {
        $ids = array_values(array_map('intval', (array) ($data['specialty_ids'] ?? [])));

        if (! array_key_exists('primary_specialty_id', $data) || $data['primary_specialty_id'] === null || $data['primary_specialty_id'] === '') {
            if ($ids === []) {
                throw ValidationException::withMessages(['specialty_ids' => 'Pilih minimal satu spesialisasi.']);
            }

            throw ValidationException::withMessages(['primary_specialty_id' => 'Spesialisasi utama wajib dipilih.']);
        }

        return [$ids, (int) $data['primary_specialty_id']];
    }

    /**
     * Validasi aturan 09 I-5 / 12 BR-02; mengembalikan id unik dengan urutan masukan.
     *
     * @param  array<int, int|string>  $specialtyIds
     * @return list<int>
     */
    private function validatedSpecialtyIds(array $specialtyIds, int $primaryId): array
    {
        $ids = array_values(array_unique(array_map('intval', $specialtyIds)));

        if ($ids === []) {
            throw ValidationException::withMessages(['specialty_ids' => 'Pilih minimal satu spesialisasi.']);
        }

        if (count($ids) > self::MAX_SPECIALTIES) {
            throw ValidationException::withMessages(['specialty_ids' => 'Spesialisasi maksimal '.self::MAX_SPECIALTIES.'.']);
        }

        if (! in_array($primaryId, $ids, true)) {
            throw ValidationException::withMessages(['primary_specialty_id' => 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.']);
        }

        if (Specialty::query()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['specialty_ids' => 'Spesialisasi yang dipilih tidak tersedia.']);
        }

        return $ids;
    }

    private function describe(string $event, Doctor $doctor): string
    {
        return __('messages.audit.'.$event, [
            'entity' => __('messages.entities.doctor'),
            'name' => $doctor->displayName,
        ]);
    }
}
