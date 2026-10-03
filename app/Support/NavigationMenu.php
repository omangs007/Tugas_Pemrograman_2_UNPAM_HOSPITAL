<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Membangun menu dari config/navigation.php.
 *
 * Menyaring tautan menuju route yang belum ada (F1-R2) dan tautan yang izinnya tidak dimiliki
 * pengguna. Penyaringan hanya kenyamanan tampilan; penegakan akses tetap di middleware/Policy.
 */
final class NavigationMenu
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function items(string $area, ?User $user = null): array
    {
        $entries = config("navigation.{$area}");

        if (! is_array($entries)) {
            return [];
        }

        $resolved = [];

        foreach ($entries as $entry) {
            $item = self::resolveEntry($entry, $user);

            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private static function resolveEntry(array $entry, ?User $user): ?array
    {
        if (isset($entry['items'])) {
            $items = self::resolveLinks($entry['items'], $user);

            return $items === [] ? null : ['group' => $entry['group'] ?? '', 'items' => $items];
        }

        if (isset($entry['children'])) {
            $children = self::resolveLinks($entry['children'], $user);

            if ($children === []) {
                return null;
            }

            return [
                'label' => $entry['label'] ?? '',
                'icon' => $entry['icon'] ?? null,
                'children' => $children,
                'active' => in_array(true, array_column($children, 'active'), true),
            ];
        }

        return self::resolveLink($entry, $user);
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array<string, mixed>>
     */
    private static function resolveLinks(array $links, ?User $user): array
    {
        $resolved = [];

        foreach ($links as $link) {
            $item = self::resolveLink($link, $user);

            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>|null
     */
    private static function resolveLink(array $link, ?User $user): ?array
    {
        $route = $link['route'] ?? null;

        if (! is_string($route) || ! Route::has($route)) {
            return null;
        }

        if (isset($link['permission']) && ! self::allows($user, (string) $link['permission'])) {
            return null;
        }

        $patterns = (array) ($link['active'] ?? [$route]);

        // array_merge (bukan `+`): 'active' dari config berisi pola dan harus ditimpa bool.
        return array_merge($link, [
            'url' => route($route),
            'active' => (bool) request()->routeIs(...$patterns),
        ]);
    }

    private static function allows(?User $user, string $permission): bool
    {
        return $user !== null && $user->canAny(explode('|', $permission));
    }
}
