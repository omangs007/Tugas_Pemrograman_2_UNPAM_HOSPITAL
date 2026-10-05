<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pemicu (c) 00 F.2: bangkitkan sesi setiap hari pukul 00.05 (idempoten, jendela booking.window_days).
Schedule::command('sessions:generate')->dailyAt('00:05')->withoutOverlapping();
