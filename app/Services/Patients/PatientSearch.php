<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Models\PatientProfile;
use App\Rules\IndonesianPhone;
use App\Support\LikePattern;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pencarian pasien oleh petugas (F7A-R16, 05 #37) dan deteksi profil mirip (BR-102, F7A-R11). Tanpa state; tidak
 * memeriksa izin. Satu kotak pencarian mengenali empat jenis istilah menurut urutan baku: nomor pasien, tanggal lahir
 * `dd/mm/yyyy`, nomor telepon, lalu nama. Karakter `%`, `_`, `\` pada istilah nama adalah teks biasa (LikePattern).
 */
final class PatientSearch
{
    /** Panjang istilah maksimum (sisanya dibuang). */
    public const MAX_TERM_LENGTH = 100;

    /** Jumlah profil mirip maksimum yang ditampilkan. */
    public const MAX_SIMILAR = 5;

    /**
     * Menambah satu kondisi pada kueri `patient_profiles`; istilah kosong tidak mengubah kueri.
     *
     * @param  Builder<PatientProfile>  $query
     * @return Builder<PatientProfile>
     */
    public function apply(Builder $query, string $term): Builder
    {
        $term = trim(mb_substr(trim(mb_scrub($term)), 0, self::MAX_TERM_LENGTH));

        if ($term === '') {
            return $query;
        }

        // (1) Nomor pasien: "P000012", "p12", "12". Tujuh digit hanya sah bila berawalan P.
        if (preg_match('/^([Pp]?)(\d{1,7})$/', $term, $matches) === 1 && ($matches[1] !== '' || strlen($matches[2]) <= 6)) {
            return $query->where('patient_number', 'P'.str_pad($matches[2], 6, '0', STR_PAD_LEFT));
        }

        // (2) Tanggal lahir dd/mm/yyyy yang sah (31/02/2026 bukan tanggal).
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $term) === 1) {
            $date = DateTimeImmutable::createFromFormat('!d/m/Y', $term);

            if ($date !== false && $date->format('d/m/Y') === $term) {
                return $query->where('birth_date', $date->format('Y-m-d'));
            }
        }

        // (3) Nomor telepon seluler yang sah setelah normalisasi: awalan.
        $phone = IndonesianPhone::normalize($term);

        if (preg_match(IndonesianPhone::PATTERN, $phone) === 1) {
            return $query->where('phone', 'like', $phone.'%');
        }

        // (4) Selain itu: nama mengandung istilah.
        return $query->where('full_name', 'like', LikePattern::contains($term));
    }

    /**
     * Profil AKTIF yang mirip dengan data yang akan dibuat: (nama sama tanpa membedakan huruf besar/kecil DAN tanggal
     * lahir sama) ATAU telepon sama setelah normalisasi. `$ignoreId` dikecualikan; maksimum lima, urut id. Klausa yang
     * datanya kosong dilewati, dan tanpa klausa sama sekali hasilnya kosong (tidak pernah "semua profil").
     *
     * @return Collection<int, PatientProfile>
     */
    public function similarTo(string $fullName, string $birthDate, string $phone, ?int $ignoreId = null): Collection
    {
        $name = mb_strtolower(trim($fullName));
        $date = trim($birthDate);
        $phone = IndonesianPhone::normalize(trim($phone));
        $byName = $name !== '' && $date !== '';

        if (! $byName && $phone === '') {
            return new Collection;
        }

        return PatientProfile::query()
            ->where('is_active', true)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->where(function (Builder $query) use ($byName, $name, $date, $phone): void {
                if ($byName) {
                    $query->where(function (Builder $same) use ($name, $date): void {
                        $same->whereRaw('lower(full_name) = ?', [$name])->where('birth_date', $date);
                    });
                }

                if ($phone !== '') {
                    $query->orWhere('phone', $phone);
                }
            })
            ->orderBy('id')
            ->limit(self::MAX_SIMILAR)
            ->get();
    }
}
