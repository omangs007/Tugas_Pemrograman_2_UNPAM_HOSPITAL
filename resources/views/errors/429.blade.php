@extends('errors.layout')

@section('title', 'Terlalu banyak permintaan')
@section('code', '429')
@section('heading', 'Terlalu banyak permintaan')
@section('message', 'Anda terlalu sering mengirim permintaan. Coba lagi dalam '.($retryAfter ?? 60).' detik.')

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url()->previous(url('/')) }}">Kembali</a>
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
