@extends('errors.layout')

@section('title', 'Akses ditolak')
@section('code', '403')
@section('heading', 'Akses ditolak')
@section('message', 'Anda tidak memiliki izin untuk mengakses halaman atau melakukan tindakan ini.')

@section('extra')
    @if (! empty($detail))
        <p class="text-body-secondary">{{ $detail }}</p>
    @endif
@endsection

@section('actions')
    @php
        // Tombol dasbor menurut tipe akun. Dibungkus try/catch agar halaman galat tetap tampil walau DB bermasalah.
        $dashboardUrl = null;
        try {
            $viewer = auth()->user();
            if ($viewer instanceof \App\Models\User) {
                if ($viewer->isStaff() && Route::has('admin.dashboard')) {
                    $dashboardUrl = route('admin.dashboard');
                } elseif ($viewer->isPatient() && Route::has('patient.dashboard')) {
                    $dashboardUrl = route('patient.dashboard');
                }
            }
        } catch (\Throwable) {
            $dashboardUrl = null;
        }
    @endphp
    <a class="btn btn-outline-secondary" href="{{ url()->previous(url('/')) }}">Kembali</a>
    @if ($dashboardUrl)
        <a class="btn btn-primary" href="{{ $dashboardUrl }}">Ke Dasbor</a>
    @else
        <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
    @endif
@endsection
