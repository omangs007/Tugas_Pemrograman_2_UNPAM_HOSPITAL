<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\Availability;
use App\Enums\ClinicType;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Clinic;
use App\Models\Page;
use App\Models\RoomCategory;
use App\Models\Specialty;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Scheduling\SessionAvailability;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Beranda #1 `home` (05 hal. 1, 07 W-02). Teks hero dari `pages.home`; banner hanya yang sedang tayang
 * (F4A-R25); enam layanan aktif pertama, empat dokter pertama beserta sesi terdekat (F4A-R16), dan tiga artikel
 * terbit terbaru. Jadwal dibaca hanya lewat DoctorAvailabilityQuery dan pembangkitan sesi harian dipicu di sini
 * (D-66, F3-R9). Blok ketersediaan kamar: kategori aktif pertama, maksimal empat (F4B-R31).
 */
class HomeController extends Controller
{
    public const FEATURED_CLINICS = 6;

    public const FEATURED_DOCTORS = 4;

    public const LATEST_ARTICLES = 3;

    public const FEATURED_ROOMS = 4;

    public function index(
        PracticeSessionGenerator $generator,
        DoctorAvailabilityQuery $query,
        SettingsService $settings,
    ): View {
        // Pemicu (b) pembangkitan sesi harian; kunci tidak menunggu, jadi halaman tetap tampil (F3-R8).
        $generator->ensureDailyHorizon();

        // DoctorAvailabilityQuery::doctors() memakai paginasi; beranda selalu menampilkan halaman pertama.
        request()->query->remove('page');

        $page = Page::forKey('home');
        $siteName = $settings->string('site.name', (string) config('app.name'));
        $tagline = $settings->string('site.tagline');

        $banners = Banner::query()
            ->currentlyActive()
            ->with(['media' => fn ($media) => $media->where('collection', 'image')])
            ->get();

        $clinics = Clinic::query()
            ->active()
            ->with(['media' => fn ($media) => $media->where('collection', 'cover')])
            ->ordered()
            ->limit(self::FEATURED_CLINICS)
            ->get();

        $doctors = $query->doctors(new AvailabilityFilter, self::FEATURED_DOCTORS)->getCollection();
        $nextSessions = [];

        foreach ($doctors as $doctor) {
            // Data publik hanya memuat spesialisasi aktif (D-56); pivot `is_primary` tetap terbawa.
            $doctor->setRelation('specialties', $doctor->specialties->filter(fn (Specialty $item): bool => $item->is_active)->values());
            $nextSessions[$doctor->id] = $this->nextSession($doctor->availabilities);
        }

        $articles = Article::query()
            ->published()
            ->newestFirst()
            ->with(['category', 'media' => fn ($media) => $media->where('collection', 'cover')])
            ->limit(self::LATEST_ARTICLES)
            ->get();

        // Blok kamar tidak memakai gambar, jadi tanpa eager load `media` (F4B-R31).
        $rooms = RoomCategory::query()
            ->active()
            ->ordered()
            ->limit(self::FEATURED_ROOMS)
            ->get();

        $specialties = Specialty::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $polyclinics = Clinic::query()->active()->where('type', ClinicType::Poliklinik->value)->ordered()->get(['id', 'name', 'slug']);

        // Judul utuh tanpa sufiks: `meta_title`, atau "{site.name} — {site.tagline}" (F4A-R5).
        $title = filled($page->meta_title)
            ? (string) $page->meta_title
            : ($tagline !== '' ? $siteName.' — '.$tagline : $siteName);

        $ogImage = $banners->first()?->firstMedia('image')?->url;

        return view('public.home', [
            'page' => $page,
            'title' => $title,
            'subtitle' => filled($page->subtitle) ? (string) $page->subtitle : $tagline,
            'metaDescription' => filled($page->meta_description) ? (string) $page->meta_description : null,
            'ogImage' => $ogImage !== null ? url($ogImage) : null,
            'banners' => $banners,
            'clinics' => $clinics,
            'doctors' => $doctors,
            'nextSessions' => $nextSessions,
            'articles' => $articles,
            'rooms' => $rooms,
            'specialtyOptions' => ['' => 'Semua spesialisasi'] + $specialties->pluck('name', 'slug')->all(),
            'clinicOptions' => ['' => 'Semua poliklinik'] + $polyclinics->pluck('name', 'slug')->all(),
            'windowDays' => max(1, $settings->bookingRules()->windowDays),
            'today' => CarbonImmutable::today(),
        ]);
    }

    /**
     * Sesi terdekat yang bukan pembatalan; kartu dokter menampilkannya sebagai "Jadwal terdekat" (F3-R25).
     *
     * @param  Collection<int, SessionAvailability>  $availabilities
     */
    private function nextSession(Collection $availabilities): ?SessionAvailability
    {
        $cancellations = [Availability::Leave, Availability::Holiday, Availability::Cancelled];

        return $availabilities->first(
            fn (SessionAvailability $item): bool => ! in_array($item->availability, $cancellations, true),
        );
    }
}
