<?php

namespace App\Http\Controllers\Public;

use App\Enums\ClinicType;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(): View
    {
        $clinics = Clinic::query()
            ->active()
            ->with(['media' => fn ($query) => $query->where('collection', 'cover')])
            ->ordered()
            ->get();

        $groups = [];

        foreach (ClinicType::cases() as $type) {
            $items = $clinics->filter(fn (Clinic $clinic): bool => $clinic->type === $type)->values();

            if ($items->isNotEmpty()) {
                $groups[] = ['type' => $type, 'clinics' => $items];
            }
        }

        return view('public.clinics.index', ['groups' => $groups, 'page' => Page::forKey('clinics-index')]);
    }

    public function show(Clinic $clinic): View
    {
        // Tanpa Policy: nonaktif = 404 untuk semua orang, termasuk staf (A-42).
        abort_unless($clinic->is_active, 404);

        $clinic->load('media');

        $doctors = $clinic->doctors()
            ->publiclyVisible()
            ->with([
                'specialties' => fn ($query) => $query->where('specialties.is_active', true),
                'media' => fn ($query) => $query->where('collection', 'photo'),
            ])
            ->ordered()
            ->get();

        return view('public.clinics.show', [
            'clinic' => $clinic,
            'doctors' => $doctors,
            'metaDescription' => Str::limit((string) ($clinic->meta_description ?: $clinic->short_description), 160, ''),
        ]);
    }
}
