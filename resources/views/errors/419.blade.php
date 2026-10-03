@extends('errors.layout')

@section('title', 'Sesi berakhir')
@section('code', '419')
@section('heading', 'Sesi berakhir')
@section('message', 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi.')

@section('actions')
    <a class="btn btn-primary" href="{{ url()->previous(url('/')) }}">Muat Ulang</a>
    <a class="btn btn-outline-secondary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
