<?php

namespace Tests\Feature;

use App\Filament\Resources\BakeryContentPageResource;
use App\Models\BakeryContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalManagedPageSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_managed_pages_are_protected_from_route_and_delete_drift(): void
    {
        foreach (['about', 'shipping', 'privacy', 'terms', 'quality'] as $slug) {
            $this->assertTrue(
                BakeryContentPageResource::isProtectedPage(new BakeryContentPage(['slug' => $slug]))
            );
        }

        $this->assertFalse(
            BakeryContentPageResource::isProtectedPage(new BakeryContentPage(['slug' => 'campaign-story']))
        );

        $source = file_get_contents(app_path('Filament/Resources/BakeryContentPageResource.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('private const PROTECTED_SLUGS', $source);
        $this->assertStringContainsString(
            '->visible(fn (BakeryContentPage $record): bool => ! self::isProtectedPage($record))',
            $source,
        );
        $this->assertStringContainsString('->bulkActions([])', $source);
    }
}
