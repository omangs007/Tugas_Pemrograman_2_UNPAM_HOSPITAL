<?php

namespace App\Http\Requests\Patient\Concerns;

use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Support\Actor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Aturan bersama `review` dan `store` (V-10/V-11, F6-R21). Profil yang dipesan SELALU milik `$this->user()`; tidak ada
 * id profil di input. Data tinjauan dibawa sebagai field tersembunyi dan divalidasi ulang sepenuhnya di `store`.
 * Normalisasi (carry fase 5): BPJS tanpa spasi dan `-`; polis tanpa spasi (regex V-10 mengizinkan `-` dan `/`).
 */
trait HasBookingRules
{
    /** Nomor BPJS diisi server dari profil (pasien mengosongkan isian). */
    protected bool $bpjsFromProfile = false;

    /** @return array<string, list<mixed>> */
    protected function bookingRules(): array
    {
        return [
            'practice_session_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('practice_sessions', 'id')],
            'payment_type' => ['bail', 'required', 'string', Rule::in(PaymentType::values())],
            'bpjs_number' => ['bail', 'nullable', 'required_if:payment_type,bpjs', 'prohibited_unless:payment_type,bpjs', 'string', 'digits:13'],
            'insurance_partner_id' => [
                'bail', 'nullable', 'required_if:payment_type,asuransi', 'prohibited_unless:payment_type,asuransi', 'integer',
                Rule::exists('insurance_partners', 'id')->where('is_active', true),
            ],
            'policy_number' => ['bail', 'nullable', 'prohibited_unless:payment_type,asuransi', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/-]+$/'],
            'complaint' => ['bail', 'nullable', 'string', 'max:500', 'regex:/\A[\s\S]*\z/u'],
            'save_bpjs' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function bookingMessages(): array
    {
        return [
            'practice_session_id.required' => 'Sesi praktik wajib dipilih.',
            'practice_session_id.integer' => 'Sesi praktik tidak ditemukan.',
            'practice_session_id.min' => 'Sesi praktik tidak ditemukan.',
            'practice_session_id.exists' => 'Sesi praktik tidak ditemukan.',
            'payment_type.required' => 'Jenis pembayaran wajib dipilih.',
            'payment_type.string' => 'Jenis pembayaran tidak valid.',
            'payment_type.in' => 'Jenis pembayaran tidak valid.',
            'bpjs_number.required_if' => 'Nomor BPJS wajib diisi untuk pembayaran BPJS.',
            'bpjs_number.prohibited_unless' => 'Nomor BPJS hanya boleh diisi bila jenis pembayaran adalah BPJS.',
            'bpjs_number.string' => 'Nomor BPJS harus terdiri dari 13 digit angka.',
            'bpjs_number.digits' => 'Nomor BPJS harus terdiri dari 13 digit angka.',
            'insurance_partner_id.required_if' => 'Mitra asuransi wajib dipilih untuk pembayaran asuransi.',
            'insurance_partner_id.prohibited_unless' => 'Mitra asuransi hanya boleh dipilih bila jenis pembayaran adalah asuransi.',
            'insurance_partner_id.integer' => 'Mitra asuransi yang dipilih tidak tersedia.',
            'insurance_partner_id.exists' => 'Mitra asuransi yang dipilih tidak tersedia.',
            'policy_number.prohibited_unless' => 'Nomor polis hanya boleh diisi bila jenis pembayaran adalah asuransi.',
            'policy_number.string' => 'Nomor polis tidak valid.',
            'policy_number.max' => 'Nomor polis maksimal 30 karakter.',
            'policy_number.regex' => 'Nomor polis hanya boleh berisi huruf, angka, garis miring, dan tanda hubung.',
            'complaint.string' => 'Keluhan tidak valid.',
            'complaint.max' => 'Keluhan maksimal 500 karakter.',
            'complaint.regex' => 'Keluhan tidak valid.',
            'save_bpjs.boolean' => 'Pilihan simpan nomor BPJS tidak valid.',
        ];
    }

    /**
     * Normalisasi sebelum validasi. Dipanggil dari `prepareForValidation()` (yang berjalan SEBELUM `authorize()`).
     */
    protected function prepareBookingInput(): void
    {
        $merge = [];

        $bpjs = $this->input('bpjs_number');

        if (is_string($bpjs)) {
            // `preg_replace` mengembalikan null untuk UTF-8 rusak: nilai mentah dibiarkan sampai validasi (ditolak),
            // tidak dijadikan kosong secara diam-diam.
            $bpjs = preg_replace('/[\s-]+/u', '', $bpjs) ?? $bpjs;
            $merge['bpjs_number'] = $bpjs === '' ? null : $bpjs;
        }

        $policy = $this->input('policy_number');

        if (is_string($policy)) {
            $policy = preg_replace('/\s+/u', '', $policy) ?? $policy;
            $merge['policy_number'] = $policy === '' ? null : $policy;
        }

        $complaint = $this->input('complaint');

        if (is_string($complaint)) {
            $complaint = trim($complaint);
            $merge['complaint'] = $complaint === '' ? null : $complaint;
        }

        $typed = array_key_exists('bpjs_number', $merge) ? $merge['bpjs_number'] : $bpjs;

        // Pasien mengosongkan isian BPJS dan profil menyimpan nomor: pakai nomor tersimpan (dekripsi hanya di server).
        if ($this->input('payment_type') === PaymentType::Bpjs->value && ($typed === null || $typed === '')) {
            $user = $this->user();
            $stored = $user instanceof User ? $user->loadMissing('patientProfile')->patientProfile?->bpjs_number : null;

            if (is_string($stored) && $stored !== '') {
                $merge['bpjs_number'] = $stored;
                $merge['save_bpjs'] = false;
                $this->bpjsFromProfile = true;
            }
        }

        $this->merge($merge);
    }

    /** Nomor BPJS diketik pasien sendiri (bukan diisi dari profil). */
    public function bpjsTyped(): bool
    {
        return $this->input('payment_type') === PaymentType::Bpjs->value
            && ! $this->bpjsFromProfile
            && is_string($this->input('bpjs_number'))
            && $this->input('bpjs_number') !== '';
    }

    /** `BookingData` dari input yang lolos validasi. */
    public function toBookingData(): BookingData
    {
        return BookingData::fromValidated($this->bookingInput($this->validated()));
    }

    /**
     * Pengalihan untuk kegagalan validasi: langkah 4 untuk sesi yang sama (tidak pernah back(); halaman tinjau adalah
     * hasil POST). Id sesi hanya dipakai bila bilangan bulat positif yang wajar.
     */
    protected function getRedirectUrl(): string
    {
        $id = $this->input('practice_session_id');

        if ((is_string($id) || is_int($id)) && preg_match('/^[1-9][0-9]{0,17}$/', (string) $id) === 1) {
            return route('patient.appointments.create', ['sesi' => (string) $id]);
        }

        return route('patient.appointments.create');
    }

    /**
     * `BookingService::precheck()` (tanpa kunci, tanpa tulis) setelah semua field lolos; kegagalan = galat pada
     * `practice_session_id` dengan pesan `BookingException`. Dibangun dari `only()` karena `validated()` belum dapat
     * dipanggil di dalam hook ini.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $profile = $user instanceof User ? $user->loadMissing('patientProfile')->patientProfile : null;
            $session = PracticeSession::query()->with(['clinic', 'doctor', 'exception'])->find((int) $this->input('practice_session_id'));

            if ($profile === null || $session === null) {
                $validator->errors()->add('practice_session_id', 'Sesi praktik tidak ditemukan.');

                return;
            }

            $input = $this->only(['payment_type', 'bpjs_number', 'policy_number', 'insurance_partner_id', 'complaint', 'save_bpjs']);

            try {
                app(BookingService::class)->precheck($profile, $session, BookingData::fromValidated($this->bookingInput($input)), Actor::patient($user));
            } catch (BookingException $e) {
                $validator->errors()->add('practice_session_id', $e->getMessage());
            }
        });
    }

    /**
     * Bentuk masukan `BookingData::fromValidated()`: referensi hanya sesuai jenis pembayaran, `save_bpjs` hanya untuk
     * nomor BPJS yang diketik pasien sendiri.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function bookingInput(array $source): array
    {
        $type = (string) ($source['payment_type'] ?? '');
        $isBpjs = $type === PaymentType::Bpjs->value;
        $isInsurance = $type === PaymentType::Asuransi->value;
        $partner = $source['insurance_partner_id'] ?? null;

        return [
            'payment_type' => $type,
            'bpjs_number' => $isBpjs ? ($source['bpjs_number'] ?? null) : null,
            'policy_number' => $isInsurance ? ($source['policy_number'] ?? null) : null,
            'insurance_partner_id' => $isInsurance && $partner !== null && $partner !== '' ? (int) $partner : null,
            'complaint' => $source['complaint'] ?? null,
            'save_bpjs' => $isBpjs && ! $this->bpjsFromProfile && filter_var($source['save_bpjs'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
