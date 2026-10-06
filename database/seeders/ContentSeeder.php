<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo konten (13 §8.3 s.d. §8.7, F4A-R27): kategori dan artikel kesehatan umum, banner beranda, album galeri,
 * FAQ, dan mitra asuransi fiktif berlabel "Contoh". Hanya `local`/`testing`; idempoten per slug atau judul.
 * `PageSeeder` (15 halaman) berjalan di semua lingkungan dan tidak ada di sini.
 */
class ContentSeeder extends Seeder
{
    /** Akun penulis artikel (DemoUserSeeder::ACCOUNTS, Admin Konten Demo). */
    public const AUTHOR_EMAIL = 'konten@unpamhospital.test';

    /** Kalimat penutup baku setiap artikel (13 §8.5). */
    public const CLOSING_SENTENCE = 'Informasi ini bersifat umum dan tidak menggantikan konsultasi dengan tenaga kesehatan.';

    /**
     * `sort_order` = nomor urut 1…n.
     *
     * @var list<array{name: string, slug: string}>
     */
    public const CATEGORIES = [
        ['name' => 'Kesehatan Umum', 'slug' => 'kesehatan-umum'],
        ['name' => 'Keluarga dan Anak', 'slug' => 'keluarga-dan-anak'],
        ['name' => 'Gaya Hidup Sehat', 'slug' => 'gaya-hidup-sehat'],
        ['name' => 'Info Layanan', 'slug' => 'info-layanan'],
    ];

    /**
     * 13 §8.5. `days` = umur terbit dalam hari (NULL = draf). `paragraphs` adalah dua paragraf orisinal yang netral;
     * kalimat penutup ditambahkan sebagai paragraf ketiga. `cover` false untuk artikel #5 (uji placeholder) dan draf #8.
     *
     * @var list<array{title: string, slug: string, category: string, days: ?int, excerpt: string, paragraphs: list<string>, cover: bool}>
     */
    public const ARTICLES = [
        [
            'title' => 'Kebiasaan Sederhana untuk Menjaga Kesehatan Jantung',
            'slug' => 'kebiasaan-sederhana-menjaga-kesehatan-jantung',
            'category' => 'gaya-hidup-sehat',
            'days' => 3,
            'excerpt' => 'Aktivitas fisik teratur, pola makan seimbang, dan istirahat cukup adalah kebiasaan umum yang mendukung kesehatan jantung.',
            'paragraphs' => [
                'Menjaga kesehatan jantung tidak selalu dimulai dari langkah yang rumit. Kebiasaan harian seperti bergerak secara teratur, memilih makanan yang beragam, dan menyediakan waktu untuk beristirahat dapat menjadi dasar yang baik bagi tubuh.',
                'Sesuaikan kegiatan dengan kemampuan Anda, misalnya berjalan kaki santai di sekitar rumah atau memilih tangga bila memungkinkan. Perubahan kecil yang dilakukan terus-menerus biasanya lebih mudah dipertahankan daripada perubahan besar yang sulit diikuti.',
            ],
            'cover' => true,
        ],
        [
            'title' => 'Mengapa Pemeriksaan Kesehatan Berkala Itu Penting',
            'slug' => 'pentingnya-pemeriksaan-kesehatan-berkala',
            'category' => 'kesehatan-umum',
            'days' => 6,
            'excerpt' => 'Pemeriksaan berkala membantu Anda mengenali kondisi tubuh dan berdiskusi dengan dokter tentang langkah pencegahan.',
            'paragraphs' => [
                'Pemeriksaan kesehatan berkala adalah kesempatan untuk mengenal kondisi tubuh Anda dan membicarakannya dengan dokter. Dari percakapan tersebut, dokter dapat menjelaskan hal-hal yang perlu diperhatikan sesuai usia, riwayat keluarga, dan kebiasaan sehari-hari.',
                'Sebelum datang, catat pertanyaan dan keluhan yang ingin Anda sampaikan, serta bawa catatan obat atau hasil pemeriksaan sebelumnya bila ada. Persiapan sederhana ini membantu pertemuan berjalan lebih terarah.',
            ],
            'cover' => true,
        ],
        [
            'title' => 'Menyiapkan Diri Sebelum Berkunjung ke Poliklinik',
            'slug' => 'menyiapkan-diri-sebelum-berkunjung-ke-poliklinik',
            'category' => 'info-layanan',
            'days' => 9,
            'excerpt' => 'Catat keluhan, bawa dokumen yang diperlukan, dan datang lebih awal agar kunjungan berjalan lancar.',
            'paragraphs' => [
                'Kunjungan ke poliklinik akan lebih lancar bila Anda menyiapkan beberapa hal sejak di rumah. Periksa jadwal dokter, pastikan sesi masih tersedia, lalu simpan bukti pendaftaran atau kode booking agar mudah ditunjukkan kepada petugas.',
                'Catat keluhan dengan singkat, siapkan identitas diri, dan bawa kartu BPJS atau polis asuransi bila Anda memakainya. Datanglah sedikit lebih awal agar ada waktu untuk proses check-in dan pemanggilan antrean.',
            ],
            'cover' => true,
        ],
        [
            'title' => 'Mengenali Tanda Kekurangan Cairan pada Anak',
            'slug' => 'mengenali-tanda-kekurangan-cairan-pada-anak',
            'category' => 'keluarga-dan-anak',
            'days' => 12,
            'excerpt' => 'Anak yang rewel, jarang buang air kecil, atau tampak lemas perlu diperhatikan; hubungi tenaga kesehatan bila ragu.',
            'paragraphs' => [
                'Anak yang kurang minum dapat terlihat lebih rewel, tampak lemas, atau jarang buang air kecil. Orang tua dapat memperhatikan kebiasaan minum anak dan menawarkan cairan secara bertahap sepanjang hari.',
                'Bila Anda ragu dengan kondisi anak atau gejalanya tidak membaik, hubungi tenaga kesehatan atau kunjungi poliklinik anak agar mendapat penjelasan yang sesuai dengan keadaan anak Anda.',
            ],
            'cover' => true,
        ],
        [
            'title' => 'Pola Tidur yang Baik untuk Tubuh yang Bugar',
            'slug' => 'pola-tidur-yang-baik-untuk-tubuh-bugar',
            'category' => 'gaya-hidup-sehat',
            'days' => 15,
            'excerpt' => 'Jadwal tidur yang konsisten dan lingkungan tidur yang nyaman membantu tubuh beristirahat dengan baik.',
            'paragraphs' => [
                'Tidur yang cukup membantu tubuh beristirahat. Jadwal tidur dan bangun yang relatif konsisten dapat membantu tubuh mengenali waktu istirahatnya.',
                'Lingkungan tidur yang nyaman, misalnya kamar yang tenang, gelap, dan sejuk, juga berperan. Mengurangi penggunaan layar menjelang tidur dan menghindari makan berat terlalu larut adalah kebiasaan yang dapat dicoba.',
            ],
            'cover' => false,
        ],
        [
            'title' => 'Cara Mencuci Tangan yang Benar',
            'slug' => 'cara-mencuci-tangan-yang-benar',
            'category' => 'kesehatan-umum',
            'days' => 20,
            'excerpt' => 'Mencuci tangan dengan sabun dan air mengalir adalah kebiasaan sederhana untuk membantu mencegah penularan penyakit.',
            'paragraphs' => [
                'Mencuci tangan dengan sabun dan air mengalir adalah kebiasaan sederhana yang dapat membantu mencegah penularan penyakit. Lakukan sebelum makan, setelah dari toilet, dan setelah beraktivitas di tempat umum.',
                'Basahi tangan, gosok seluruh permukaan termasuk punggung tangan, sela jari, dan ujung kuku, lalu bilas hingga bersih dan keringkan dengan lap bersih. Bila sabun dan air belum tersedia, cairan pembersih tangan dapat menjadi pilihan sementara.',
            ],
            'cover' => true,
        ],
        [
            'title' => 'Panduan Pendaftaran Online di UNPAM HOSPITAL',
            'slug' => 'panduan-pendaftaran-online-unpam-hospital',
            'category' => 'info-layanan',
            'days' => 1,
            'excerpt' => 'Langkah membuat akun, memilih dokter dan sesi, hingga menyimpan bukti pendaftaran.',
            'paragraphs' => [
                'Pendaftaran kunjungan rawat jalan dapat dilakukan lewat situs ini. Buat akun, masuk, lalu pilih poliklinik, dokter, tanggal, dan sesi yang tersedia.',
                'Setelah mengisi data pendaftaran dan konfirmasi, simpan bukti pendaftaran yang memuat kode booking dan nomor antrean. Anda juga dapat mendaftar langsung di loket pendaftaran. Seluruh data pada situs prototipe ini adalah data contoh.',
            ],
            'cover' => true,
        ],
        [
            'title' => 'Draf: Menjaga Kesehatan di Musim Hujan',
            'slug' => 'draf-menjaga-kesehatan-di-musim-hujan',
            'category' => 'kesehatan-umum',
            'days' => null,
            'excerpt' => 'Draf yang belum terbit — tidak boleh tampil di publik (uji status).',
            'paragraphs' => [
                'Pergantian cuaca sering mengubah kebiasaan sehari-hari. Menjaga kebersihan, memilih makanan yang segar, dan beristirahat cukup dapat membantu tubuh tetap bugar.',
                'Perhatikan juga kebersihan lingkungan sekitar rumah dan siapkan perlengkapan seperti jas hujan agar tidak mudah basah kuyup saat bepergian.',
            ],
            'cover' => false,
        ],
    ];

    /**
     * 13 §8.3. Aktif sejak awal hari seeding, tanpa tanggal selesai; gambar 1600x600. `sort_order` = nomor urut.
     *
     * @var list<array{title: string, subtitle: string, label: string, url: string}>
     */
    public const BANNERS = [
        ['title' => 'Selamat Datang di UNPAM HOSPITAL', 'subtitle' => 'Informasi layanan, dokter, dan jadwal praktik dalam satu tempat.', 'label' => 'Cari Dokter', 'url' => '/dokter'],
        ['title' => 'Daftar Kunjungan Lebih Mudah', 'subtitle' => 'Buat akun, pilih dokter dan sesi, lalu simpan bukti pendaftaran Anda.', 'label' => 'Daftar Kunjungan', 'url' => '/pasien/pendaftaran/baru'],
        ['title' => 'Lihat Jadwal Dokter Terbaru', 'subtitle' => 'Jadwal di halaman ini sama dengan jadwal pada form pendaftaran.', 'label' => 'Jadwal Dokter', 'url' => '/jadwal-dokter'],
    ];

    /**
     * 13 §8.6. Empat foto per album; alt dan keterangan foto memakai judul album lengkap (13 §9.2, F4A-R36).
     *
     * @var list<array{title: string, slug: string, description: string}>
     */
    public const GALLERIES = [
        ['title' => 'Lobi dan Area Pendaftaran (Foto Contoh)', 'slug' => 'lobi-dan-area-pendaftaran', 'description' => 'Ilustrasi area penerimaan pasien.'],
        ['title' => 'Fasilitas Pelayanan (Foto Contoh)', 'slug' => 'fasilitas-pelayanan', 'description' => 'Ilustrasi ruang pelayanan dan penunjang.'],
        ['title' => 'Edukasi Kesehatan (Foto Contoh)', 'slug' => 'edukasi-kesehatan', 'description' => 'Ilustrasi kegiatan penyuluhan kesehatan.'],
    ];

    /**
     * 13 §8.4. Terbit semua; `sort_order` = nomor urut 1…12.
     *
     * @var list<array{category: string, question: string, answer: string}>
     */
    public const FAQS = [
        ['category' => 'pendaftaran', 'question' => 'Bagaimana cara mendaftar kunjungan rawat jalan secara online?', 'answer' => 'Buat akun di halaman Daftar, masuk, pilih poliklinik, dokter, tanggal dan sesi, isi data pendaftaran, lalu konfirmasi. Bukti pendaftaran berisi kode booking dan nomor antrean dapat dicetak atau diunduh.'],
        ['category' => 'pendaftaran', 'question' => 'Sampai berapa hari ke depan saya dapat mendaftar?', 'answer' => 'Pendaftaran online dapat dilakukan untuk sesi sampai 30 hari ke depan.'],
        ['category' => 'pendaftaran', 'question' => 'Kapan pendaftaran online untuk suatu sesi ditutup?', 'answer' => 'Pendaftaran online ditutup 60 menit sebelum sesi dimulai. Setelah itu Anda dapat mendaftar langsung di loket selama sesi belum berakhir dan kuota masih tersedia.'],
        ['category' => 'pendaftaran', 'question' => 'Bagaimana cara membatalkan pendaftaran?', 'answer' => 'Buka Riwayat Pendaftaran lalu pilih Batalkan, paling lambat 120 menit sebelum sesi dimulai. Setelah batas itu, hubungi rumah sakit melalui telepon atau WhatsApp.'],
        ['category' => 'pendaftaran', 'question' => 'Apa yang perlu saya bawa saat datang?', 'answer' => 'Tunjukkan kode booking atau bukti pendaftaran, identitas diri, serta kartu BPJS atau polis asuransi bila memakainya. Check-in dibuka 60 menit sebelum sesi dimulai.'],
        ['category' => 'pendaftaran', 'question' => 'Apakah saya bisa mendaftar tanpa akun?', 'answer' => 'Bisa. Datang ke loket pendaftaran; petugas dapat membuat profil pasien dan mendaftarkan kunjungan Anda. Kuota dibagi bersama pendaftaran online.'],
        ['category' => 'bpjs', 'question' => 'Apakah nomor BPJS wajib diisi?', 'answer' => 'Hanya jika Anda memilih jenis pembayaran BPJS Kesehatan (13 digit). Untuk pembayaran umum atau asuransi, nomor BPJS tidak diminta.'],
        ['category' => 'bpjs', 'question' => 'Apakah saya perlu mengisi NIK?', 'answer' => 'Tidak. Sistem ini tidak meminta NIK.'],
        ['category' => 'layanan', 'question' => 'Apakah jadwal dokter dapat berubah?', 'answer' => 'Dapat berubah, misalnya karena cuti. Bila jadwal Anda terdampak, petugas menindaklanjuti dan Anda menerima pemberitahuan di akun atau melalui telepon yang tercatat.'],
        ['category' => 'layanan', 'question' => 'Apakah ketersediaan kamar rawat inap bersifat real-time?', 'answer' => 'Tidak. Ketersediaan diperbarui manual oleh petugas dan waktu pembaruan terakhir ditampilkan. Hubungi rumah sakit untuk memastikan ketersediaan.'],
        ['category' => 'umum', 'question' => 'Bagaimana data pribadi saya dilindungi?', 'answer' => 'Data hanya digunakan untuk pendaftaran kunjungan, nomor BPJS disimpan terenkripsi, dan akses dibatasi bagi petugas berwenang. Baca Kebijakan Privasi untuk rinciannya.'],
        ['category' => 'umum', 'question' => 'Bagaimana menghubungi rumah sakit?', 'answer' => 'Gunakan halaman Kontak dan Lokasi atau tombol WhatsApp. Tombol WhatsApp hanya membuka aplikasi WhatsApp dan pesan dikirim sendiri oleh Anda; bukan pengiriman otomatis. Nomor pada prototipe ini adalah placeholder.'],
    ];

    /**
     * 13 §8.7. Fiktif dan berlabel "Contoh"; aktif, tanpa situs web; logo PNG 400x200 (`code` = teks pada gambar).
     *
     * @var list<array{name: string, slug: string, type: string, code: string, description: string}>
     */
    public const PARTNERS = [
        ['name' => 'Asuransi Contoh Sehat', 'slug' => 'asuransi-contoh-sehat', 'type' => 'asuransi', 'code' => 'ACS', 'description' => 'Contoh mitra asuransi kesehatan untuk keperluan prototipe. Bukan kerja sama nyata.'],
        ['name' => 'Asuransi Contoh Sentosa', 'slug' => 'asuransi-contoh-sentosa', 'type' => 'asuransi', 'code' => 'ACN', 'description' => 'Contoh mitra asuransi jiwa dan kesehatan. Bukan kerja sama nyata.'],
        ['name' => 'Asuransi Contoh Prima', 'slug' => 'asuransi-contoh-prima', 'type' => 'asuransi', 'code' => 'ACP', 'description' => 'Contoh mitra asuransi kesehatan keluarga. Bukan kerja sama nyata.'],
        ['name' => 'PT Contoh Karya Mandiri', 'slug' => 'pt-contoh-karya-mandiri', 'type' => 'perusahaan', 'code' => 'PCKM', 'description' => 'Contoh perusahaan rekanan layanan kesehatan karyawan. Bukan kerja sama nyata.'],
    ];

    private MediaService $media;

    private PlaceholderImageGenerator $generator;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ContentSeeder DILARANG di APP_ENV='.app()->environment().'. Seeder konten contoh hanya untuk data demo.');
        }

        $author = User::query()->where('email', self::AUTHOR_EMAIL)->first();

        if ($author === null) {
            throw new RuntimeException('Akun Admin Konten Demo belum ada. Jalankan DemoUserSeeder lebih dulu.');
        }

        $this->media = app(MediaService::class);
        $this->generator = app(PlaceholderImageGenerator::class);

        $this->seedCategories();
        $this->seedArticles($author);
        $this->seedBanners();
        $this->seedGalleries();
        $this->seedFaqs();
        $this->seedPartners();
    }

    private function seedCategories(): void
    {
        foreach (self::CATEGORIES as $index => $row) {
            if (ArticleCategory::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Kategori artikel {$row['name']} sudah ada, dilewati.");

                continue;
            }

            ArticleCategory::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => null,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function seedArticles(User $author): void
    {
        $categories = ArticleCategory::query()->pluck('id', 'slug');

        foreach (self::ARTICLES as $index => $row) {
            if (Article::query()->withTrashed()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Artikel {$row['title']} sudah ada, dilewati.");

                continue;
            }

            $categoryId = $categories->get($row['category']);

            if ($categoryId === null) {
                throw new RuntimeException("Kategori artikel {$row['category']} belum ada.");
            }

            $published = $row['days'] !== null;

            $article = Article::query()->create([
                'article_category_id' => $categoryId,
                'author_user_id' => $author->id,
                'title' => $row['title'],
                'slug' => $row['slug'],
                'excerpt' => $row['excerpt'],
                'body' => self::body($row['paragraphs']),
                'status' => $published ? ArticleStatus::Published : ArticleStatus::Draft,
                'published_at' => $published ? now()->subDays($row['days']) : null,
                'meta_title' => null,
                'meta_description' => null,
            ]);

            if ($row['cover']) {
                $this->attach('cover', 'ARTIKEL '.($index + 1), 1200, 675, 'cover', $article, 'Ilustrasi contoh '.$row['title']);
            }
        }
    }

    private function seedBanners(): void
    {
        foreach (self::BANNERS as $index => $row) {
            if (Banner::query()->where('title', $row['title'])->exists()) {
                $this->command?->warn("Banner {$row['title']} sudah ada, dilewati.");

                continue;
            }

            $banner = Banner::query()->create([
                'title' => $row['title'],
                'subtitle' => $row['subtitle'],
                'button_label' => $row['label'],
                'button_url' => $row['url'],
                'sort_order' => $index + 1,
                'is_active' => true,
                'starts_at' => now()->startOfDay(),
                'ends_at' => null,
            ]);

            $this->attach('image', 'BANNER '.($index + 1), 1600, 600, 'image', $banner, 'Banner contoh '.$row['title']);
        }
    }

    private function seedGalleries(): void
    {
        foreach (self::GALLERIES as $index => $row) {
            if (Gallery::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Album {$row['title']} sudah ada, dilewati.");

                continue;
            }

            $gallery = Gallery::query()->create([
                'title' => $row['title'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'is_published' => true,
                'sort_order' => $index + 1,
            ]);

            foreach (range(1, 4) as $number) {
                $this->attach(
                    'photos',
                    'ALBUM '.($index + 1).'-'.$number,
                    1200,
                    800,
                    'photos',
                    $gallery,
                    "Foto contoh {$row['title']} {$number}",
                    "Foto contoh: {$row['title']}, foto {$number} dari 4.",
                );
            }
        }
    }

    private function seedFaqs(): void
    {
        foreach (self::FAQS as $index => $row) {
            if (Faq::query()->where('question', $row['question'])->exists()) {
                $this->command?->warn("FAQ \"{$row['question']}\" sudah ada, dilewati.");

                continue;
            }

            Faq::query()->create([
                'category' => $row['category'],
                'question' => $row['question'],
                'answer' => $row['answer'],
                'sort_order' => $index + 1,
                'is_published' => true,
            ]);
        }
    }

    private function seedPartners(): void
    {
        foreach (self::PARTNERS as $index => $row) {
            if (InsurancePartner::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Mitra {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $partner = InsurancePartner::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'type' => $row['type'],
                'description' => $row['description'],
                'website_url' => null,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $this->attach('logo', $row['code'], 400, 200, 'logo', $partner, 'Logo contoh '.$row['name']);
        }
    }

    /**
     * Isi artikel: dua paragraf orisinal ditambah kalimat penutup baku (HTML disanitasi trait saat disimpan).
     *
     * @param  list<string>  $paragraphs
     */
    public static function body(array $paragraphs): string
    {
        $html = '';

        foreach ([...$paragraphs, self::CLOSING_SENTENCE] as $paragraph) {
            $html .= '<p>'.e($paragraph).'</p>';
        }

        return $html;
    }

    /**
     * Membuat satu gambar contoh lewat GD lalu menyimpannya lewat MediaService; berkas sementara selalu dihapus.
     */
    private function attach(string $type, string $label, int $width, int $height, string $collection, Model $owner, string $alt, ?string $caption = null): void
    {
        $file = $this->generator->make($type, $label, $width, $height);

        try {
            $this->media->store($file, $collection, $owner, ['alt_text' => $alt, 'caption' => $caption]);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
