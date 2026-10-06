<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\SanitizesRichText;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Halaman statis sistem (09 §6.1): lima belas baris tetap yang dibuat PageSeeder di semua lingkungan. Tidak ada
 * aksi tambah/hapus. `body` (rich text, disanitasi) hanya untuk key di `KEYS_WITH_BODY`. `Page::forKey()` tidak
 * pernah 404: baris yang hilang digantikan instance tak tersimpan dari `DEFAULTS` (F4A-R4).
 *
 * @property string $key
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $body
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property bool $is_system
 * @property int|null $updated_by
 * @property-read string $seo_title
 * @property-read string|null $seo_description
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasMedia, SanitizesRichText;

    /** @var list<string> */
    public const KEYS = [
        'home', 'about', 'vision-mission', 'clinics-index', 'doctors-index', 'schedules-index', 'facilities-index',
        'rooms-index', 'insurance-info', 'registration-guide', 'articles-index', 'galleries-index', 'faq', 'contact',
        'privacy-policy',
    ];

    /** @var list<string> */
    public const KEYS_WITH_BODY = ['about', 'vision-mission', 'insurance-info', 'registration-guide', 'privacy-policy'];

    /** @var array<string, string> key => nama route publik (00 E.5) */
    public const ROUTES = [
        'home' => 'home',
        'about' => 'about',
        'vision-mission' => 'vision-mission',
        'clinics-index' => 'clinics.index',
        'doctors-index' => 'doctors.index',
        'schedules-index' => 'schedules.index',
        'facilities-index' => 'facilities.index',
        'rooms-index' => 'rooms.index',
        'insurance-info' => 'insurance',
        'registration-guide' => 'registration-guide',
        'articles-index' => 'articles.index',
        'galleries-index' => 'galleries.index',
        'faq' => 'faq',
        'contact' => 'contact',
        'privacy-policy' => 'privacy',
    ];

    /**
     * Judul, subjudul, dan meta description bawaan (13 §8.8): sumber PageSeeder dan cadangan `forKey()`.
     *
     * @var array<string, array{title: string, subtitle: string, meta_description: string}>
     */
    public const DEFAULTS = [
        'home' => [
            'title' => 'UNPAM HOSPITAL',
            'subtitle' => 'Layanan kesehatan yang ramah dan terpercaya (contoh)',
            'meta_description' => 'Informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan UNPAM HOSPITAL.',
        ],
        'about' => [
            'title' => 'Profil Rumah Sakit',
            'subtitle' => 'Mengenal UNPAM HOSPITAL',
            'meta_description' => 'Profil UNPAM HOSPITAL, rumah sakit prototipe di Universitas Pamulang Kampus 2 Viktor.',
        ],
        'vision-mission' => [
            'title' => 'Visi dan Misi',
            'subtitle' => 'Arah dan komitmen pelayanan',
            'meta_description' => 'Visi dan misi contoh UNPAM HOSPITAL.',
        ],
        'clinics-index' => [
            'title' => 'Layanan dan Poliklinik',
            'subtitle' => 'Pilih layanan sesuai kebutuhan Anda',
            'meta_description' => 'Daftar poliklinik dan layanan penunjang UNPAM HOSPITAL.',
        ],
        'doctors-index' => [
            'title' => 'Cari Dokter',
            'subtitle' => 'Cari berdasarkan nama, spesialisasi, poliklinik, atau tanggal praktik',
            'meta_description' => 'Cari dokter UNPAM HOSPITAL berdasarkan nama, spesialisasi, poliklinik, dan tanggal.',
        ],
        'schedules-index' => [
            'title' => 'Jadwal Dokter',
            'subtitle' => 'Jadwal praktik per minggu',
            'meta_description' => 'Jadwal praktik dokter UNPAM HOSPITAL per minggu dengan sisa kuota.',
        ],
        'facilities-index' => [
            'title' => 'Fasilitas',
            'subtitle' => 'Fasilitas medis, penunjang, dan umum',
            'meta_description' => 'Daftar fasilitas UNPAM HOSPITAL.',
        ],
        'rooms-index' => [
            'title' => 'Kamar Rawat Inap',
            'subtitle' => 'Ringkasan kategori kamar dan ketersediaan',
            'meta_description' => 'Kategori kamar rawat inap, kapasitas, dan ketersediaan yang diperbarui manual.',
        ],
        'insurance-info' => [
            'title' => 'Informasi BPJS dan Asuransi',
            'subtitle' => 'Persyaratan dan pembayaran',
            'meta_description' => 'Informasi contoh tentang BPJS Kesehatan dan asuransi di UNPAM HOSPITAL.',
        ],
        'registration-guide' => [
            'title' => 'Panduan Pendaftaran Pasien',
            'subtitle' => 'Langkah mendaftar kunjungan rawat jalan',
            'meta_description' => 'Panduan mendaftar kunjungan rawat jalan online maupun di loket.',
        ],
        'articles-index' => [
            'title' => 'Artikel Kesehatan',
            'subtitle' => 'Informasi kesehatan umum',
            'meta_description' => 'Artikel kesehatan umum dari UNPAM HOSPITAL.',
        ],
        'galleries-index' => [
            'title' => 'Galeri',
            'subtitle' => 'Foto-foto contoh',
            'meta_description' => 'Galeri foto UNPAM HOSPITAL.',
        ],
        'faq' => [
            'title' => 'Pertanyaan yang Sering Diajukan',
            'subtitle' => 'Jawaban atas pertanyaan umum',
            'meta_description' => 'Pertanyaan umum tentang pendaftaran, BPJS, layanan, dan data pribadi.',
        ],
        'contact' => [
            'title' => 'Kontak dan Lokasi',
            'subtitle' => 'Hubungi kami atau kunjungi kami',
            'meta_description' => 'Alamat, telepon, WhatsApp, e-mail, peta, dan formulir kontak UNPAM HOSPITAL.',
        ],
        'privacy-policy' => [
            'title' => 'Kebijakan Privasi',
            'subtitle' => 'Cara kami memperlakukan data Anda',
            'meta_description' => 'Kebijakan privasi dan persetujuan penggunaan data pasien UNPAM HOSPITAL.',
        ],
    ];

    /**
     * `key` dan `is_system` sengaja tidak termasuk: diisi seeder lewat forceFill() (F4A-R24).
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'body',
        'meta_title',
        'meta_description',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Halaman sistem untuk `$key`. Bila barisnya belum ada, mengembalikan instance TAK TERSIMPAN dari
     * `DEFAULTS` (judul, subjudul, meta description; `body` NULL) sehingga halaman publik tetap 200 (F4A-R4).
     * Key di luar `KEYS` melempar InvalidArgumentException.
     */
    public static function forKey(string $key): self
    {
        if (! in_array($key, self::KEYS, true)) {
            throw new InvalidArgumentException("Halaman sistem tidak dikenal: {$key}.");
        }

        $page = static::query()->where('key', $key)->first();

        if ($page !== null) {
            return $page;
        }

        $defaults = self::DEFAULTS[$key];

        return (new self)->forceFill([
            'key' => $key,
            'title' => $defaults['title'],
            'subtitle' => $defaults['subtitle'],
            'body' => null,
            'meta_title' => null,
            'meta_description' => $defaults['meta_description'],
            'is_system' => true,
            'updated_by' => null,
        ]);
    }

    /** Halaman ini boleh memiliki isi rich text (`pages.body`). */
    public function hasBody(): bool
    {
        return in_array($this->key, self::KEYS_WITH_BODY, true);
    }

    /** Nama route publik halaman ini (00 E.5). */
    public function publicRouteName(): string
    {
        return self::ROUTES[$this->key];
    }

    /**
     * Judul SEO: `meta_title`, atau `title` bila kosong (F4A-R5).
     *
     * @return Attribute<string, never>
     */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_title) ? (string) $this->meta_title : (string) $this->title);
    }

    /**
     * Deskripsi SEO: `meta_description`, lalu `subtitle`, lalu NULL (layout memakai `seo.default_meta_description`).
     *
     * @return Attribute<string|null, never>
     */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (filled($this->meta_description)) {
                return (string) $this->meta_description;
            }

            return filled($this->subtitle) ? (string) $this->subtitle : null;
        });
    }

    /**
     * Kolom rich text yang disanitasi saat disimpan (F2-R14).
     *
     * @return list<string>
     */
    public function richTextAttributes(): array
    {
        return ['body'];
    }
}
