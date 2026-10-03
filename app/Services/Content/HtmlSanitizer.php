<?php

declare(strict_types=1);

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use Mews\Purifier\Facades\Purifier;

final class HtmlSanitizer
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Bersihkan HTML rich text dengan HTMLPurifier (profil rich_text, 10 §7.2).
     */
    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $purified = Purifier::clean($html, 'rich_text');

        return $this->postProcess(is_string($purified) ? $purified : '');
    }

    private function postProcess(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $loaded ? $document->documentElement : null;

        if ($root === null) {
            return '';
        }

        $host = $this->appHost();

        foreach (iterator_to_array($root->getElementsByTagName('img')) as $image) {
            $source = $this->normalizeImageSource($image->getAttribute('src'), $host);

            if ($source === null) {
                $image->parentNode?->removeChild($image);

                continue;
            }

            $image->setAttribute('src', $source);
        }

        foreach (iterator_to_array($root->getElementsByTagName('a')) as $link) {
            $this->normalizeLink($link, $host);
        }

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Hanya path /storage/... yang lolos; URL absolut ke host aplikasi dinormalkan menjadi path.
     */
    private function normalizeImageSource(string $source, string $host): ?string
    {
        $source = trim($source);

        if ($source === '' || preg_match('/[\x00-\x1F\x7F]/', $source) === 1 || str_contains($source, '\\')) {
            return null;
        }

        if (str_starts_with($source, '/storage/')) {
            return $this->isSafeStoragePath($source) ? $source : null;
        }

        $parts = parse_url($source);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $host === '') {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || strtolower($parts['host']) !== $host) {
            return null;
        }

        $path = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return $this->isSafeStoragePath($path) ? $path : null;
    }

    private function isSafeStoragePath(string $path): bool
    {
        return str_starts_with($path, '/storage/')
            && strlen($path) > strlen('/storage/')
            && ! str_contains($path, '..');
    }

    /**
     * Lapis kedua setelah HTMLPurifier: skema selain http/https/mailto/tel dibuang,
     * tautan eksternal dipaksa target _blank + rel noopener noreferrer.
     */
    private function normalizeLink(DOMElement $link, string $host): void
    {
        if (! $link->hasAttribute('href')) {
            return;
        }

        $compact = preg_replace('/[\x00-\x20\x7F]+/', '', $link->getAttribute('href')) ?? '';

        if ($compact === '') {
            $link->removeAttribute('href');

            return;
        }

        $scheme = preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $match) === 1 ? strtolower($match[1]) : null;

        if ($scheme !== null && ! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            $link->removeAttribute('href');

            return;
        }

        $protocolRelative = str_starts_with($compact, '//');

        if (! in_array($scheme, ['http', 'https'], true) && ! $protocolRelative) {
            return;
        }

        $linkHost = strtolower((string) parse_url($protocolRelative ? 'http:'.$compact : $compact, PHP_URL_HOST));

        if ($linkHost === '' || $linkHost !== $host) {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function appHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }
}
