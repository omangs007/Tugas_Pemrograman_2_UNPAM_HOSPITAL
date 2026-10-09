<?php

/*
|--------------------------------------------------------------------------
| Definisi menu (blueprint 05 §3, 10 §7.6)
|--------------------------------------------------------------------------
|
| Dibaca oleh App\Support\NavigationMenu. Entri bisa berupa:
|   - tautan : label, route, icon?, permission? ("a|b" = salah satu izin), active? (pola routeIs), emphasis?
|   - dropdown: label, children (daftar tautan)
|   - grup    : group, items (daftar tautan), hanya untuk area admin
| Tautan ke route yang belum ada (fase berikutnya) dibuang otomatis oleh NavigationMenu.
| Semua route di sini harus tanpa parameter wajib.
|
*/

return [

    // Menu utama situs publik (05 §3.1). Tombol aksi "Daftar Kunjungan"/"Masuk" ada di layout.
    'public' => [
        ['label' => 'Tentang Kami', 'children' => [
            ['label' => 'Profil', 'route' => 'about'],
            ['label' => 'Visi & Misi', 'route' => 'vision-mission'],
        ]],
        ['label' => 'Layanan', 'route' => 'clinics.index', 'active' => ['clinics.*']],
        ['label' => 'Dokter', 'children' => [
            ['label' => 'Daftar Dokter', 'route' => 'doctors.index', 'active' => ['doctors.*']],
            ['label' => 'Jadwal Dokter', 'route' => 'schedules.index'],
        ]],
        ['label' => 'Fasilitas', 'children' => [
            ['label' => 'Fasilitas', 'route' => 'facilities.index', 'active' => ['facilities.*']],
            ['label' => 'Kamar Rawat Inap', 'route' => 'rooms.index', 'active' => ['rooms.*']],
        ]],
        ['label' => 'Informasi', 'children' => [
            ['label' => 'BPJS & Asuransi', 'route' => 'insurance'],
            ['label' => 'Panduan Pendaftaran', 'route' => 'registration-guide'],
            ['label' => 'Artikel', 'route' => 'articles.index', 'active' => ['articles.*']],
            ['label' => 'Galeri', 'route' => 'galleries.index', 'active' => ['galleries.*']],
            ['label' => 'FAQ', 'route' => 'faq'],
        ]],
        ['label' => 'Kontak', 'route' => 'contact'],
    ],

    // Navigasi portal pasien (05 §3.4). "Keluar" (POST logout) dirender langsung oleh layout.
    'patient' => [
        ['label' => 'Dashboard', 'route' => 'patient.dashboard', 'icon' => 'bi-house-door'],
        ['label' => 'Daftar Kunjungan', 'route' => 'patient.appointments.create', 'icon' => 'bi-calendar-plus', 'emphasis' => true, 'active' => ['patient.appointments.create', 'patient.appointments.review']],
        ['label' => 'Riwayat Pendaftaran', 'route' => 'patient.appointments.index', 'icon' => 'bi-clock-history', 'active' => ['patient.appointments.index', 'patient.appointments.show', 'patient.appointments.proof']],
        ['label' => 'Profil dan Akun', 'route' => 'patient.profile.edit', 'icon' => 'bi-person-gear', 'active' => ['patient.profile.*']],
    ],

    // Tautan cepat footer (05 §3.3). "Kebijakan Privasi" dirender langsung oleh layout (Route::has('privacy')).
    'footer' => [
        ['label' => 'Cari Dokter', 'route' => 'doctors.index', 'active' => ['doctors.*']],
        ['label' => 'Jadwal Dokter', 'route' => 'schedules.index'],
        ['label' => 'Layanan', 'route' => 'clinics.index', 'active' => ['clinics.*']],
        ['label' => 'Kamar Rawat Inap', 'route' => 'rooms.index', 'active' => ['rooms.*']],
        ['label' => 'BPJS & Asuransi', 'route' => 'insurance'],
        ['label' => 'Panduan Pendaftaran', 'route' => 'registration-guide'],
        ['label' => 'Artikel', 'route' => 'articles.index', 'active' => ['articles.*']],
        ['label' => 'Galeri', 'route' => 'galleries.index', 'active' => ['galleries.*']],
        ['label' => 'FAQ', 'route' => 'faq'],
        ['label' => 'Kontak', 'route' => 'contact'],
    ],

    // Sidebar admin (05 §3.5). Penyaringan izin hanya kenyamanan; penegakan di middleware/Policy.
    'admin' => [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'permission' => 'dashboard.view', 'active' => ['admin.dashboard', 'admin.dashboard.*']],

        ['group' => 'Pelayanan Pasien', 'items' => [
            ['label' => 'Pendaftaran', 'route' => 'admin.appointments.index', 'icon' => 'bi-journal-check', 'permission' => 'appointments.view', 'active' => ['admin.appointments.*']],
            ['label' => 'Antrean Hari Ini', 'route' => 'admin.queue.index', 'icon' => 'bi-people', 'permission' => 'appointments.view', 'active' => ['admin.queue.*']],
            ['label' => 'Pasien', 'route' => 'admin.patients.index', 'icon' => 'bi-person-lines-fill', 'permission' => 'patients.view', 'active' => ['admin.patients.*']],
            ['label' => 'Pesan Kontak', 'route' => 'admin.messages.index', 'icon' => 'bi-envelope', 'permission' => 'messages.manage', 'active' => ['admin.messages.*']],
        ]],

        ['group' => 'Dokter dan Jadwal', 'items' => [
            ['label' => 'Dokter', 'route' => 'admin.doctors.index', 'icon' => 'bi-person-badge', 'permission' => 'doctors.view', 'active' => ['admin.doctors.*']],
            ['label' => 'Poliklinik dan Spesialisasi', 'route' => 'admin.clinics.index', 'icon' => 'bi-hospital', 'permission' => 'clinics.manage', 'active' => ['admin.clinics.*', 'admin.specialties.*']],
            ['label' => 'Jadwal Praktik', 'route' => 'admin.schedules.index', 'icon' => 'bi-calendar-week', 'permission' => 'schedules.view', 'active' => ['admin.schedules.*', 'admin.exceptions.*', 'admin.sessions.index', 'admin.sessions.create', 'admin.sessions.store', 'admin.sessions.edit', 'admin.sessions.update', 'admin.sessions.status', 'admin.sessions.destroy']],
            ['label' => 'Sesi Terdampak', 'route' => 'admin.sessions.review', 'icon' => 'bi-exclamation-triangle', 'permission' => 'sessions.review', 'active' => ['admin.sessions.review', 'admin.sessions.resolve']],
        ]],

        ['group' => 'Fasilitas dan Kamar', 'items' => [
            ['label' => 'Kamar dan Kapasitas', 'route' => 'admin.room-categories.index', 'icon' => 'bi-building', 'permission' => 'facilities.manage|rooms.update-availability', 'active' => ['admin.room-categories.*']],
            ['label' => 'Fasilitas', 'route' => 'admin.facilities.index', 'icon' => 'bi-heart-pulse', 'permission' => 'facilities.manage', 'active' => ['admin.facilities.*']],
        ]],

        ['group' => 'Konten Website', 'items' => [
            ['label' => 'Halaman', 'route' => 'admin.pages.index', 'icon' => 'bi-file-earmark-text', 'permission' => 'content.manage', 'active' => ['admin.pages.*']],
            ['label' => 'Banner', 'route' => 'admin.banners.index', 'icon' => 'bi-images', 'permission' => 'content.manage', 'active' => ['admin.banners.*']],
            ['label' => 'Artikel', 'route' => 'admin.articles.index', 'icon' => 'bi-newspaper', 'permission' => 'content.manage', 'active' => ['admin.articles.*']],
            ['label' => 'Kategori Artikel', 'route' => 'admin.article-categories.index', 'icon' => 'bi-tags', 'permission' => 'content.manage', 'active' => ['admin.article-categories.*']],
            ['label' => 'Galeri', 'route' => 'admin.galleries.index', 'icon' => 'bi-image', 'permission' => 'content.manage', 'active' => ['admin.galleries.*']],
            ['label' => 'FAQ', 'route' => 'admin.faqs.index', 'icon' => 'bi-question-circle', 'permission' => 'content.manage', 'active' => ['admin.faqs.*']],
            ['label' => 'Mitra Asuransi', 'route' => 'admin.insurance-partners.index', 'icon' => 'bi-shield-check', 'permission' => 'content.manage', 'active' => ['admin.insurance-partners.*']],
        ]],

        ['group' => 'Sistem', 'items' => [
            ['label' => 'Pengaturan Website', 'route' => 'admin.settings.edit', 'icon' => 'bi-gear', 'permission' => 'settings.manage', 'active' => ['admin.settings.*']],
            ['label' => 'Akun Staf', 'route' => 'admin.staff.index', 'icon' => 'bi-people-fill', 'permission' => 'staff.manage', 'active' => ['admin.staff.*']],
            ['label' => 'Hak Akses', 'route' => 'admin.roles.index', 'icon' => 'bi-key', 'permission' => 'staff.manage', 'active' => ['admin.roles.*']],
            ['label' => 'Audit Log', 'route' => 'admin.audit-logs.index', 'icon' => 'bi-clipboard-data', 'permission' => 'audit.view', 'active' => ['admin.audit-logs.*']],
        ]],
    ],

    // Tab modul (10 §3.2, F2-R11): dibaca <x-module-tabs group="...">. Disaring Route::has dan izin oleh
    // NavigationMenu::items('modules.{group}'); penegakan akses tetap di middleware/Policy.
    'modules' => [
        'clinics' => [
            ['label' => 'Poliklinik', 'route' => 'admin.clinics.index', 'permission' => 'clinics.manage', 'active' => ['admin.clinics.*']],
            ['label' => 'Spesialisasi', 'route' => 'admin.specialties.index', 'permission' => 'clinics.manage', 'active' => ['admin.specialties.*']],
        ],
        // Halaman #35 (F3-R19). Tab ke route yang belum ada (Sesi Terdampak sampai fase 7) otomatis tersembunyi.
        'schedules' => [
            ['label' => 'Templat Mingguan', 'route' => 'admin.schedules.index', 'permission' => 'schedules.view', 'active' => ['admin.schedules.*']],
            ['label' => 'Sesi per Tanggal', 'route' => 'admin.sessions.index', 'permission' => 'schedules.view', 'active' => ['admin.sessions.index', 'admin.sessions.create', 'admin.sessions.store', 'admin.sessions.edit', 'admin.sessions.update', 'admin.sessions.status', 'admin.sessions.destroy']],
            ['label' => 'Pengecualian', 'route' => 'admin.exceptions.index', 'permission' => 'schedules.view', 'active' => ['admin.exceptions.*']],
            ['label' => 'Sesi Terdampak', 'route' => 'admin.sessions.review', 'permission' => 'sessions.review', 'active' => ['admin.sessions.review', 'admin.sessions.resolve']],
        ],
        // Halaman #39 (F4A-R28). Titik masuk = tab Halaman. Tab ke route yang belum ada otomatis tersembunyi.
        'content' => [
            ['label' => 'Halaman', 'route' => 'admin.pages.index', 'permission' => 'content.manage', 'active' => ['admin.pages.*']],
            ['label' => 'Banner', 'route' => 'admin.banners.index', 'permission' => 'content.manage', 'active' => ['admin.banners.*']],
            ['label' => 'Artikel', 'route' => 'admin.articles.index', 'permission' => 'content.manage', 'active' => ['admin.articles.*']],
            ['label' => 'Kategori Artikel', 'route' => 'admin.article-categories.index', 'permission' => 'content.manage', 'active' => ['admin.article-categories.*']],
            ['label' => 'Galeri', 'route' => 'admin.galleries.index', 'permission' => 'content.manage', 'active' => ['admin.galleries.*']],
            ['label' => 'FAQ', 'route' => 'admin.faqs.index', 'permission' => 'content.manage', 'active' => ['admin.faqs.*']],
            ['label' => 'Mitra Asuransi', 'route' => 'admin.insurance-partners.index', 'permission' => 'content.manage', 'active' => ['admin.insurance-partners.*']],
        ],
        // Halaman #36 (F4B-R28). Tab ke route yang belum ada otomatis tersembunyi. "Kamar dan Kapasitas" juga terbuka
        // bagi pemegang `rooms.update-availability` (Petugas Pendaftaran, D-12).
        'facilities' => [
            ['label' => 'Kamar dan Kapasitas', 'route' => 'admin.room-categories.index', 'permission' => 'facilities.manage|rooms.update-availability', 'active' => ['admin.room-categories.*']],
            ['label' => 'Fasilitas', 'route' => 'admin.facilities.index', 'permission' => 'facilities.manage', 'active' => ['admin.facilities.*']],
        ],
        // Halaman #38 (F7A-R24). "Antrean Hari Ini" tersembunyi otomatis sampai route antrean ada.
        'appointments' => [
            ['label' => 'Pendaftaran', 'route' => 'admin.appointments.index', 'permission' => 'appointments.view', 'active' => ['admin.appointments.*']],
            ['label' => 'Antrean Hari Ini', 'route' => 'admin.queue.index', 'permission' => 'appointments.view', 'active' => ['admin.queue.*']],
        ],
    ],

];
