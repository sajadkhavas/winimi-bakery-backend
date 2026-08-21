<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenerateSitemap extends Command
{
    private const SITEMAP_URL = 'https://winimibakery.com/sitemap.xml';

    private const LEGACY_MARKERS = [
        'localhost:8080',
        'toolmaster',
        'gas-generators',
        'plc-equipment',
        '/brands/siemens',
    ];

    protected $signature = 'sitemap:generate';

    protected $description = 'Validate the canonical Winimi storefront sitemap';

    public function handle(): int
    {
        try {
            $response = Http::accept('application/xml')
                ->timeout(15)
                ->get(self::SITEMAP_URL);
        } catch (Throwable $exception) {
            $this->error('SITEMAP_FETCH_FAILED='.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('SITEMAP_HTTP_STATUS='.$response->status());

            return self::FAILURE;
        }

        $body = $response->body();
        $normalized = strtolower($body);

        if (! str_contains($body, '<urlset') || ! str_contains($body, '<loc>')) {
            $this->error('SITEMAP_XML_INVALID');

            return self::FAILURE;
        }

        foreach (self::LEGACY_MARKERS as $marker) {
            if (str_contains($normalized, strtolower($marker))) {
                $this->error('LEGACY_SITEMAP_MARKER='.$marker);

                return self::FAILURE;
            }
        }

        preg_match_all('/<loc>(.*?)<\/loc>/is', $body, $matches);

        $locations = array_values(array_filter(array_map(
            static fn (string $location): string => html_entity_decode(
                trim($location),
                ENT_QUOTES | ENT_XML1,
                'UTF-8',
            ),
            $matches[1] ?? [],
        )));

        if ($locations === []) {
            $this->error('SITEMAP_URL_COUNT=0');

            return self::FAILURE;
        }

        foreach ($locations as $location) {
            if (! str_starts_with($location, 'https://winimibakery.com/')) {
                $this->error('NON_CANONICAL_SITEMAP_URL='.$location);

                return self::FAILURE;
            }

            foreach (['/account', '/cart', '/checkout', '/payment'] as $privatePrefix) {
                if (str_starts_with(
                    parse_url($location, PHP_URL_PATH) ?: '/',
                    $privatePrefix,
                )) {
                    $this->error('PRIVATE_URL_IN_SITEMAP='.$location);

                    return self::FAILURE;
                }
            }
        }

        $this->info('SITEMAP_CANONICAL_URL='.self::SITEMAP_URL);
        $this->info('SITEMAP_URL_COUNT='.count($locations));
        $this->info('SITEMAP_LEGACY_MARKERS=0');
        $this->info('SITEMAP_VALIDATION=PASS');
        $this->warn('Sitemap is generated dynamically by the storefront; no backend sitemap file was written.');

        return self::SUCCESS;
    }
}
