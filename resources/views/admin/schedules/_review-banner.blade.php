{{-- Banner "N sesi perlu ditindaklanjuti" untuk ketiga tab #35 (F3-R19). N = sesi needs_review yang belum berakhir.
     Hanya angka agregat, tanpa data pasien (04 §6 V1). Tautan hanya bila route ada (fase 7) dan pengguna berizin. --}}
@php
    $reviewCount = \App\Models\PracticeSession::query()->needsReview()->notEnded()->count();
    $reviewLink = \Illuminate\Support\Facades\Route::has('admin.sessions.review') && auth()->user()?->can('sessions.review');
@endphp
@if ($reviewCount > 0)
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" data-review-banner>
        <span><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $reviewCount }} sesi perlu ditindaklanjuti petugas pendaftaran.</span>
        @if ($reviewLink)
            <a href="{{ route('admin.sessions.review') }}" class="btn btn-sm btn-outline-dark">Buka Sesi Terdampak</a>
        @endif
    </div>
@endif
