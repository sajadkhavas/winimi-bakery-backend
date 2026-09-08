<?php

namespace App\Services\Store;

use App\Models\Redirect;

final class StorefrontRedirectService
{
    /** @var list<int> */
    private const ALLOWED_STATUS_CODES = [301, 302, 307, 308];

    private const MAX_CHAIN_DEPTH = 10;

    /** @var list<string> */
    private const PROTECTED_SOURCE_PREFIXES = [
        '/account',
        '/admin',
        '/api',
        '/assets',
        '/auth',
        '/cart',
        '/checkout',
        '/health',
        '/manifest.webmanifest',
        '/payment',
        '/robots.txt',
        '/s',
        '/sitemap.xml',
        '/up',
    ];

    public static function normalizeSource(string $value): ?string
    {
        $value = trim($value);
        if (! self::isSafeInternalLocation($value, allowSuffix: false)) {
            return null;
        }

        $path = self::normalizedPath($value);
        if ($path === null || self::isProtectedSource($path)) {
            return null;
        }

        return $path;
    }

    public static function normalizeTarget(string $value): ?string
    {
        $value = trim($value);
        if (! self::isSafeInternalLocation($value, allowSuffix: true)) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts)) {
            return null;
        }

        $path = self::normalizedPath((string) ($parts['path'] ?? '/'));
        if ($path === null) {
            return null;
        }

        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?'.$parts['query']
            : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== ''
            ? '#'.$parts['fragment']
            : '';

        return $path.$query.$fragment;
    }

    public static function isAllowedStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, self::ALLOWED_STATUS_CODES, true);
    }

    public static function isProtectedSource(string $path): bool
    {
        if ($path === '/') {
            return true;
        }

        foreach (self::PROTECTED_SOURCE_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function wouldCreateLoop(
        string $source,
        string $target,
        ?int $ignoreRedirectId = null,
    ): bool {
        $normalizedSource = self::normalizeSource($source);
        $normalizedTarget = self::normalizeTarget($target);
        if ($normalizedSource === null || $normalizedTarget === null) {
            return true;
        }

        $current = self::pathFromTarget($normalizedTarget);
        if ($current === null || $current === $normalizedSource) {
            return true;
        }

        $visited = [$normalizedSource => true];

        for ($depth = 0; $depth < self::MAX_CHAIN_DEPTH; $depth++) {
            if (isset($visited[$current])) {
                return true;
            }
            $visited[$current] = true;

            $redirect = Redirect::query()
                ->where('from_url', $current)
                ->where('is_active', true)
                ->when(
                    $ignoreRedirectId !== null,
                    fn ($query) => $query->whereKeyNot($ignoreRedirectId),
                )
                ->first();

            if ($redirect === null) {
                return false;
            }

            if (! self::isAllowedStatusCode((int) $redirect->status_code)) {
                return true;
            }

            $nextTarget = self::normalizeTarget((string) $redirect->to_url);
            if ($nextTarget === null) {
                return true;
            }

            $nextPath = self::pathFromTarget($nextTarget);
            if ($nextPath === null) {
                return true;
            }

            $current = $nextPath;
        }

        return true;
    }

    /**
     * @return array{location: string, statusCode: int}|null
     */
    public function resolve(string $source, bool $incrementHit = false): ?array
    {
        $current = self::normalizeSource($source);
        if ($current === null) {
            return null;
        }

        $visited = [];
        $firstRedirect = null;
        $firstStatusCode = null;
        $location = null;

        for ($depth = 0; $depth < self::MAX_CHAIN_DEPTH; $depth++) {
            if (isset($visited[$current])) {
                return null;
            }
            $visited[$current] = true;

            $redirect = Redirect::query()
                ->where('from_url', $current)
                ->where('is_active', true)
                ->first();

            if ($redirect === null) {
                break;
            }

            $statusCode = (int) $redirect->status_code;
            $target = self::normalizeTarget((string) $redirect->to_url);
            if (! self::isAllowedStatusCode($statusCode) || $target === null) {
                return null;
            }

            $targetPath = self::pathFromTarget($target);
            if ($targetPath === null || isset($visited[$targetPath])) {
                return null;
            }

            $firstRedirect ??= $redirect;
            $firstStatusCode ??= $statusCode;
            $location = $target;
            $current = $targetPath;
        }

        if ($firstRedirect === null || $firstStatusCode === null || $location === null) {
            return null;
        }

        if (Redirect::query()->where('from_url', $current)->where('is_active', true)->exists()) {
            return null;
        }

        if ($incrementHit) {
            Redirect::query()->whereKey($firstRedirect->getKey())->increment('hit_count');
        }

        return [
            'location' => $location,
            'statusCode' => $firstStatusCode,
        ];
    }

    private static function pathFromTarget(string $target): ?string
    {
        $parts = parse_url($target);
        if (! is_array($parts)) {
            return null;
        }

        return self::normalizedPath((string) ($parts['path'] ?? '/'));
    }

    private static function normalizedPath(string $value): ?string
    {
        $parts = parse_url($value);
        if (! is_array($parts)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private static function isSafeInternalLocation(string $value, bool $allowSuffix): bool
    {
        if (
            $value === ''
            || strlen($value) > 2_048
            || ! str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }

        $decoded = rawurldecode($value);
        if (
            str_starts_with($decoded, '//')
            || str_contains($decoded, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1
        ) {
            return false;
        }

        $parts = parse_url($value);
        if (
            ! is_array($parts)
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            return false;
        }

        if (! $allowSuffix && (isset($parts['query']) || isset($parts['fragment']))) {
            return false;
        }

        $decodedPath = rawurldecode((string) ($parts['path'] ?? '/'));
        foreach (explode('/', $decodedPath) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
