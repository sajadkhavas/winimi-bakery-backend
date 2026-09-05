<?php

namespace App\Support\Content;

use JsonException;
use RuntimeException;

final class SeoGuideManifest
{
    public const DEFAULT_PATH = 'database/content/winimi-seo-guides-v1.json';
    public const VERSION = 'winimi-seo-guides-v1';
    public const TOPIC = 'راهنمای انتخاب و سفارش';
    public const GUIDE_COUNT = 5;

    /**
     * @return array{path: string, sha256: string, version: string, topic: string, guides: array<int, array<string, mixed>>}
     */
    public static function load(?string $manifest = null): array
    {
        $value = $manifest ?: self::DEFAULT_PATH;
        $candidate = str_starts_with($value, DIRECTORY_SEPARATOR)
            ? $value
            : base_path($value);
        $path = realpath($candidate);

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("SEO guide manifest is not readable: {$candidate}");
        }

        try {
            $decoded = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'SEO guide manifest JSON is invalid: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('SEO guide manifest root must be an object.');
        }

        if (($decoded['version'] ?? null) !== self::VERSION) {
            throw new RuntimeException('Unsupported SEO guide manifest version.');
        }

        if (($decoded['topic'] ?? null) !== self::TOPIC) {
            throw new RuntimeException('SEO guide manifest topic is not the canonical F29S topic.');
        }

        $sourceGuides = $decoded['guides'] ?? null;
        if (! is_array($sourceGuides) || count($sourceGuides) !== self::GUIDE_COUNT) {
            throw new RuntimeException('SEO guide manifest must contain exactly '.self::GUIDE_COUNT.' guides.');
        }

        $guides = [];
        $seenSlugs = [];

        foreach ($sourceGuides as $index => $source) {
            if (! is_array($source)) {
                throw new RuntimeException("guides[{$index}] must be an object.");
            }

            $slug = self::requiredString($source, 'slug', 160, $index);
            $title = self::requiredString($source, 'title', 180, $index);
            $excerpt = self::requiredString($source, 'excerpt', 500, $index);
            $category = self::requiredString($source, 'category', 120, $index);
            $content = self::requiredString($source, 'content', null, $index);

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                throw new RuntimeException("guides[{$index}].slug must be a lowercase ASCII slug.");
            }

            if (isset($seenSlugs[$slug])) {
                throw new RuntimeException("Duplicate SEO guide slug: {$slug}");
            }
            $seenSlugs[$slug] = true;

            if ($category !== self::TOPIC) {
                throw new RuntimeException("guides[{$index}].category must equal the canonical topic.");
            }

            $tags = $source['tags'] ?? null;
            if (! is_array($tags) || $tags === [] || count($tags) > 12) {
                throw new RuntimeException("guides[{$index}].tags must contain between 1 and 12 tags.");
            }
            $tags = array_values(array_unique(array_map(
                static function (mixed $tag) use ($index): string {
                    if (! is_string($tag) || trim($tag) === '' || mb_strlen(trim($tag)) > 80) {
                        throw new RuntimeException("guides[{$index}].tags contains an invalid tag.");
                    }

                    return trim($tag);
                },
                $tags,
            )));

            $author = $source['author'] ?? null;
            if ($author !== null && (! is_string($author) || trim($author) === '' || mb_strlen(trim($author)) > 120)) {
                throw new RuntimeException("guides[{$index}].author is invalid.");
            }
            $author = is_string($author) ? trim($author) : null;

            self::assertSafeHtml($content, $index);

            $guides[] = [
                'slug' => $slug,
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => $content,
                'category' => $category,
                'tags' => $tags,
                'author' => $author,
            ];
        }

        return [
            'path' => $path,
            'sha256' => hash_file('sha256', $path),
            'version' => self::VERSION,
            'topic' => self::TOPIC,
            'guides' => $guides,
        ];
    }

    /** @param array<string, mixed> $source */
    private static function requiredString(array $source, string $key, ?int $maximum, int $index): string
    {
        $value = $source[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("guides[{$index}].{$key} is required.");
        }

        $value = trim($value);
        if ($maximum !== null && mb_strlen($value) > $maximum) {
            throw new RuntimeException("guides[{$index}].{$key} exceeds {$maximum} characters.");
        }

        return $value;
    }

    private static function assertSafeHtml(string $content, int $index): void
    {
        $forbidden = [
            '/<\s*(script|style|iframe|object|embed|form|input|button)\b/i',
            '/\son[a-z]+\s*=/i',
            '/javascript\s*:/i',
            '/data\s*:\s*text\/html/i',
        ];

        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                throw new RuntimeException("guides[{$index}].content contains forbidden HTML.");
            }
        }

        if (mb_strlen(strip_tags($content)) < 500) {
            throw new RuntimeException("guides[{$index}].content is too thin for publication.");
        }
    }
}
