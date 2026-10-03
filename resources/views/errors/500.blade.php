@extends('errors.layout')

@section('title', 'Terjadi kesalahan pada server')
@section('code', '500')
@section('heading', 'Terjadi kesalahan pada server')
@section('message', 'Kami mencatat masalah ini. Silakan coba lagi beberapa saat lagi.')

@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
