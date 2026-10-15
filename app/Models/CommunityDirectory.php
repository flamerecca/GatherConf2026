<?php

namespace App\Models;

final class CommunityDirectory
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return array_values(config('communities', []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $community) {
            if (($community['slug'] ?? null) === $slug) {
                return $community;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $community
     */
    public static function name(array $community, ?string $locale = null): string
    {
        return $community['name'][$locale ?? app()->getLocale()] ?? $community['name']['zh_TW'];
    }
}
