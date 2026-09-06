<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Throwable;

class SitemapManager extends Page
{
    private const SITEMAP_URL = 'https://winimibakery.com/sitemap.xml';

    private const LEGACY_MARKERS = [
        'localhost:8080',
        'toolmaster',
        'gas-generators',
        'plc-equipment',
        '/brands/siemens',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Sitemap';

    protected static ?string $title = 'مدیریت Sitemap';

    protected static ?string $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.sitemap-manager';

    public string $sitemapUrl = self::SITEMAP_URL;

    public ?string $lastChecked = null;

    public ?int $urlCount = null;

    public bool $reachable = false;

    public bool $legacyDetected = false;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->loadStatus();
    }

    public function loadStatus(): void
    {
        $this->lastChecked = now()->format('Y/m/d H:i:s');
        $this->urlCount = null;
        $this->reachable = false;
        $this->legacyDetected = false;
        $this->statusMessage = null;

        try {
            $response = Http::accept('application/xml')
                ->timeout(15)
                ->get($this->sitemapUrl);
        } catch (Throwable $exception) {
            $this->statusMessage = 'خطا در دریافت Sitemap: '.$exception->getMessage();

            return;
        }

        if (! $response->successful()) {
            $this->statusMessage = 'پاسخ Sitemap با HTTP '.$response->status().' دریافت شد.';

            return;
        }

        $body = $response->body();
        $normalized = strtolower($body);

        foreach (self::LEGACY_MARKERS as $marker) {
            if (str_contains($normalized, strtolower($marker))) {
                $this->legacyDetected = true;
                $this->statusMessage = 'اثر Sitemap قدیمی ToolMaster شناسایی شد: '.$marker;

                return;
            }
        }

        if (! str_contains($body, '<urlset') || ! str_contains($body, '<loc>')) {
            $this->statusMessage = 'ساختار XML Sitemap معتبر نیست.';

            return;
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
            $this->statusMessage = 'Sitemap هیچ URL قابل بررسی ندارد.';

            return;
        }

        foreach ($locations as $location) {
            if (! str_starts_with($location, 'https://winimibakery.com/')) {
                $this->statusMessage = 'URL خارج از دامنه اصلی وینیمی در Sitemap وجود دارد: '.$location;

                return;
            }
        }

        $this->urlCount = count($locations);
        $this->reachable = true;
        $this->statusMessage = 'Sitemap اصلی وینیمی سالم و بدون اثر ToolMaster است.';
    }

    public function refreshSitemapStatus(): void
    {
        $this->loadStatus();

        $notification = Notification::make()
            ->title($this->reachable ? 'Sitemap وینیمی سالم است' : 'Sitemap نیاز به بررسی دارد')
            ->body($this->statusMessage);

        if ($this->reachable) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }
}
