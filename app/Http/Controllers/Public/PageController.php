<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\InsurancePartner;
use App\Models\Page;
use Illuminate\View\View;

/**
 * Halaman publik berbasis tabel `pages`: #2 Profil, #3 Visi dan Misi, #16 BPJS dan Asuransi, #17 Panduan Pendaftaran,
 * dan Kebijakan Privasi (11 §4.1). Judul, subjudul, SEO, dan isi dibaca lewat Page::forKey(), yang tidak pernah 404:
 * baris yang belum ada diganti teks bawaan (F4A-R4). Isi rich text sudah tersanitasi saat disimpan.
 */
class PageController extends Controller
{
    public function about(): View
    {
        return $this->render('about', 'public.about');
    }

    public function visionMission(): View
    {
        return $this->render('vision-mission', 'public.vision-mission');
    }

    public function insurance(): View
    {
        return $this->render('insurance-info', 'public.insurance', [
            'partners' => InsurancePartner::query()->active()->ordered()->with('media')->get(),
        ]);
    }

    public function registrationGuide(): View
    {
        return $this->render('registration-guide', 'public.registration-guide');
    }

    public function privacy(): View
    {
        return $this->render('privacy-policy', 'public.privacy');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function render(string $key, string $view, array $extra = []): View
    {
        return view($view, ['page' => Page::forKey($key)] + $extra);
    }
}
