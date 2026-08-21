<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitemapCanonicalizationTest extends TestCase
{
    public function test_backend_sitemap_redirects_to_canonical_storefront_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertStatus(301)
            ->assertRedirect('https://winimibakery.com/sitemap.xml');
    }

    public function test_legacy_static_backend_sitemap_is_not_tracked(): void
    {
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }
}
