<?php

namespace App\Support\Content;

use JsonException;
use RuntimeException;

final class TrustReconciliationManifest
{
    public const DEFAULT_PATH = 'database/content/winimi-trust-reconciliation-v1.json';

    public const VERSION = 'winimi-trust-reconciliation-v1';

    /** @var array<int, string> */
    private const ALLOWED_SLUGS = ['shipping', 'terms', 'privacy'];

    /**
     * @return array{path: string, sha256: string, version: string, pages: array<int, array{slug: string, replacements: array<int, array{from: string, to: string}>}>}
     */
    public static function load(?string $manifest = null): array
    {
        $value = $manifest ?: self::DEFAULT_PATH;
        $candidate = str_starts_with($value, DIRECTORY_SEPARATOR) ? $value : base_path($value);
        $path = realpath($candidate);

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Trust reconciliation manifest is not readable: {$candidate}");
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Trust reconciliation manifest JSON is invalid: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if (! is_array($decoded) || ($decoded['version'] ?? null) !== self::VERSION) {
            throw new RuntimeException('Unsupported trust reconciliation manifest version.');
        }

        $sourcePages = $decoded['pages'] ?? null;
        if (! is_array($sourcePages) || $sourcePages === []) {
            throw new RuntimeException('Trust reconciliation manifest must contain pages.');
        }

        $pages = [];
        $seen = [];

        foreach ($sourcePages as $pageIndex => $sourcePage) {
            if (! is_array($sourcePage)) {
                throw new RuntimeException("pages[{$pageIndex}] must be an object.");
            }

            $slug = trim((string) ($sourcePage['slug'] ?? ''));
            if (! in_array($slug, self::ALLOWED_SLUGS, true)) {
                throw new RuntimeException("pages[{$pageIndex}].slug is not an allowed trust page.");
            }
            if (isset($seen[$slug])) {
                throw new RuntimeException("Duplicate trust page slug: {$slug}");
            }
            $seen[$slug] = true;

            $sourceReplacements = $sourcePage['replacements'] ?? null;
            if (! is_array($sourceReplacements) || $sourceReplacements === []) {
                throw new RuntimeException("pages[{$pageIndex}].replacements must not be empty.");
            }

            $replacements = [];
            foreach ($sourceReplacements as $replacementIndex => $sourceReplacement) {
                if (! is_array($sourceReplacement)) {
                    throw new RuntimeException("pages[{$pageIndex}].replacements[{$replacementIndex}] must be an object.");
                }

                $from = trim((string) ($sourceReplacement['from'] ?? ''));
                $to = trim((string) ($sourceReplacement['to'] ?? ''));

                if ($from === '' || $to === '' || $from === $to) {
                    throw new RuntimeException("pages[{$pageIndex}].replacements[{$replacementIndex}] is invalid.");
                }

                self::assertSafeReplacement($to, $pageIndex, $replacementIndex);

                $replacements[] = ['from' => $from, 'to' => $to];
            }

            $pages[] = ['slug' => $slug, 'replacements' => $replacements];
        }

        sort($seen);

        return [
            'path' => $path,
            'sha256' => hash_file('sha256', $path),
            'version' => self::VERSION,
            'pages' => $pages,
        ];
    }

    private static function assertSafeReplacement(string $value, int $pageIndex, int $replacementIndex): void
    {
        $forbiddenPatterns = [
            '/<\s*(script|style|iframe|object|embed|form|input|button)\b/i',
            '/\son[a-z]+\s*=/i',
            '/javascript\s*:/i',
            '/پرداخت آنلاین[^.]{0,40}غیرفعال/u',
            '/ورود پیامکی[^.]{0,40}فعال نیست/u',
            '/ارسال سراسری/u',
            '/تضمین/u',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                throw new RuntimeException(
                    "pages[{$pageIndex}].replacements[{$replacementIndex}].to contains forbidden content.",
                );
            }
        }
    }
}
