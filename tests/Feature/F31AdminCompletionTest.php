<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\WebPushSubscriptionResource;
use App\Models\NavigationItem;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class F31AdminCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_navigation_has_an_explicit_public_contract(): void
    {
        $footer = NavigationItem::query()->create([
            'label' => 'راهنمای فوتر',
            'href' => '/shipping',
            'placement' => 'footer',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        NavigationItem::query()->create([
            'label' => 'فقط هدر',
            'href' => '/about',
            'placement' => 'header',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->getJson('/api/store/navigation?placement=footer')
            ->assertOk()
            ->assertJsonPath('data.0.id', $footer->getKey())
            ->assertJsonPath('meta.placement', 'footer')
            ->assertJsonMissing(['label' => 'فقط هدر']);

        $this->getJson('/api/store/navigation?placement=invalid')->assertUnprocessable();
    }

    public function test_admin_completion_settings_are_seeded_on_the_existing_authority(): void
    {
        foreach ([
            'pwa.name',
            'pwa.shortcuts',
            'pwa.offline_title',
            'integrations.google_tag_mode',
            'integrations.search_console_verification',
            'consent.analytics_enabled',
        ] as $key) {
            $this->assertTrue(StoreSetting::query()->where('key', $key)->where('is_public', true)->exists());
        }
    }

    public function test_legacy_resources_are_hidden_without_deleting_their_data(): void
    {
        $this->assertFalse(ProductResource::shouldRegisterNavigation());
        $this->assertFalse(BlogPostResource::shouldRegisterNavigation());
    }

    public function test_push_subscription_inventory_is_operator_only_and_read_only(): void
    {
        $role = Role::findOrCreate('panel_user', 'web');
        $operator = User::query()->create([
            'name' => 'Panel Operator',
            'email' => 'f31-panel-operator@example.test',
            'password' => bcrypt('password'),
        ]);
        $operator->assignRole($role);
        $this->actingAs($operator);

        $this->assertTrue(WebPushSubscriptionResource::canViewAny());
        $this->assertFalse(WebPushSubscriptionResource::canCreate());
        $this->assertFalse(WebPushSubscriptionResource::canEdit(null));
        $this->assertFalse(WebPushSubscriptionResource::canDelete(null));
    }
}
