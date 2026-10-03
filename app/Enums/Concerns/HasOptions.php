<?php

namespace App\Enums\Concerns;

/**
 * Pembantu untuk enum string-backed: nilai mentah (Rule::enum, whereIn) dan opsi <select>.
 * Enum pemakai wajib punya method label(): string.
 */
trait HasOptions
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array<string, string> nilai => label
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
