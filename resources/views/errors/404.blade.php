@extends('errors.layout')

@section('title', 'Halaman tidak ditemukan')
@section('code', '404')
@section('heading', 'Halaman tidak ditemukan')
@section('message', 'Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.')

@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
    @if (Route::has('doctors.index'))
        <a class="btn btn-outline-primary" href="{{ route('doctors.index') }}">Cari Dokter</a>
    @endif
    @if (Route::has('schedules.index'))
        <a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Jadwal Dokter</a>
    @endif
@endsection
