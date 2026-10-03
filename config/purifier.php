<?php

/**
 * Konfigurasi HTMLPurifier (mews/purifier) untuk UNPAM HOSPITAL.
 *
 * Profil `rich_text` dipakai oleh App\Services\Content\HtmlSanitizer untuk lima kolom rich text
 * (pages.body, articles.body, clinics.description, facilities.description, room_categories.description).
 * Profil `default` hanya untuk teks pendek (p, br, strong, em).
 *
 * Tiga aturan yang tidak dapat diekspresikan lewat konfigurasi HTMLPurifier (img hanya /storage/...,
 * URL absolut ke host aplikasi dinormalkan ke path, tautan eksternal target _blank + rel noopener
 * noreferrer) ditegakkan oleh HtmlSanitizer sebagai pasca-proses DOM.
 *
 * Catatan: `settings` di sini menggantikan seluruh `settings` bawaan paket (mergeConfigFrom hanya
 * menggabung kunci level atas).
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em',
            'AutoFormat.RemoveEmpty' => true,
        ],
        'rich_text' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,h2,h3,h4,strong,em,u,ul,ol,li,'
                .'a[href|title|target|rel],img[src|alt|width|height],blockquote,'
                .'table,thead,tbody,tr,th,td',
            'Attr.AllowedFrameTargets' => ['_blank'],
            'Attr.AllowedRel' => ['noopener', 'noreferrer'],
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true],
            'URI.Host' => parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: null,
            'URI.DisableExternalResources' => true,
            'CSS.AllowedProperties' => [],
            'Attr.EnableID' => false,
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.AutoParagraph' => false,
            'Core.RemoveInvalidImg' => true,
            'HTML.MaxImgLength' => 1600,
        ],
    ],
];
