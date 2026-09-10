<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BakeryCategorySurfaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_surface_controls_are_exposed_by_catalog_api(): void
    {
        $category = BakeryCategory::create([
            'name' => 'کوکی‌های خانگی',
            'slug' => 'cookies',
            'is_active' => true,
            'show_on_home' => false,
            'show_in_footer' => true,
            'sort_order' => 8,
            'home_sort_order' => 2,
            'footer_sort_order' => 4,
        ]);

        $this->getJson('/api/catalog/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $category->public_id)
            ->assertJsonPath('data.0.showOnHome', false)
            ->assertJsonPath('data.0.showInFooter', true)
            ->assertJsonPath('data.0.sortOrder', 8)
            ->assertJsonPath('data.0.homeSortOrder', 2)
            ->assertJsonPath('data.0.footerSortOrder', 4);
    }

    public function test_surface_orders_fall_back_to_catalog_sort_order(): void
    {
        BakeryCategory::create([
            'name' => 'کیک و دسر',
            'slug' => 'cakes',
            'is_active' => true,
            'sort_order' => 7,
            'home_sort_order' => null,
            'footer_sort_order' => null,
        ]);

        $this->getJson('/api/catalog/categories')
            ->assertOk()
            ->assertJsonPath('data.0.showOnHome', true)
            ->assertJsonPath('data.0.showInFooter', true)
            ->assertJsonPath('data.0.homeSortOrder', 7)
            ->assertJsonPath('data.0.footerSortOrder', 7);
    }
}
