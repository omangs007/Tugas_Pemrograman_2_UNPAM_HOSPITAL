<?php

declare(strict_types=1);

namespace App\Services\Content;

use Mews\Purifier\Facades\Purifier;

final class HtmlSanitizer
{
    /**
     * Bersihkan HTML rich text dengan HTMLPurifier (profil rich_text, 10 §7.2).
     */
    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $purified = Purifier::clean($html, 'rich_text');

        return is_string($purified) ? $purified : '';
    }
}
