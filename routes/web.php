<?php

use Illuminate\Support\Facades\Route;

// Beranda sementara (F1-R3); HomeController dibangun di fase 4a.
Route::view('/', 'public.home')->name('home');
