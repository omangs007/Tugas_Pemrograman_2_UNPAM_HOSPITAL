<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Peta tersemat (10 §3.4, F4B-R33, OFF-04). Selalu menyediakan kotak pengganti statis berisi alamat dan tombol
 * "Petunjuk Arah"; iframe `map.embed_url` hanya dirender bila berupa URL https. `initMapEmbeds()` di `app.js`
 * menyembunyikan iframe bila peramban offline atau iframe tidak `load` dalam 8000 ms. Tanpa JavaScript iframe tampil
 * di atas kotak pengganti.
 */
final class MapEmbed extends Component
{
    public int $height;

    public string $embedUrl;

    public string $directionsUrl;

    public string $address;

    public function __construct(
        SettingsService $settings,
        int $height = 360,
        public string $title = 'Peta lokasi UNPAM HOSPITAL',
    ) {
        $this->height = $height;

        $embed = $settings->string('map.embed_url');
        $directions = $settings->string('map.directions_url');

        // Pertahanan berlapis (pengaturan hanya diisi admin): iframe wajib https; tautan wajib http(s).
        $this->embedUrl = preg_match('#^https://#i', $embed) === 1 ? $embed : '';
        $this->directionsUrl = preg_match('#^https?://#i', $directions) === 1 ? $directions : '';
        $this->address = $settings->string('contact.address');
    }

    public function render(): View
    {
        return view('components.map-embed');
    }
}
