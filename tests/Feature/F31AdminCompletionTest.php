<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\BrandResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\FaqResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ReviewResource;
use App\Filament\Resources\SettingResource;
use App\Filament\Resources\SitePageResource;
use App\Filament\Resources\SliderResource;
use App\Filament\Resources\StoreSettingResource;
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

    public function test_admin_completion_migration_preserves_existing_operator_values(): void
    {
        StoreSetting::query()
            ->where('key', 'pwa.name')
            ->update([
                'value' => 'نام ثبت‌شده مدیر',
                'label' => 'عنوان قدیمی',
                'is_public' => false,
            ]);

        $migration = require database_path('migrations/2026_09_10_100000_seed_admin_completion_settings.php');
        $migration->up();

        $setting = StoreSetting::query()->where('key', 'pwa.name')->firstOrFail();

        $this->assertSame('نام ثبت‌شده مدیر', $setting->value);
        $this->assertSame('نام کامل اپ', $setting->label);
        $this->assertSame('string', $setting->type);
        $this->assertTrue($setting->is_public);
    }

    public function test_store_settings_use_specialized_safe_editors(): void
    {
        foreach ([
            'pwa.shortcuts' => 'shortcuts',
            'pwa.theme_color' => 'color',
            'pwa.background_color' => 'color',
            'integrations.google_tag_mode' => 'tag-mode',
            'integrations.google_tag_id' => 'tag-id',
            'integrations.search_console_verification' => 'search-console',
            'consent.analytics_enabled' => 'boolean',
            'pwa.offline_description' => 'long-text',
            'home.hero_primary_href' => 'internal-path',
            'home.hero_image_url' => 'media-url',
        ] as $key => $expectedEditor) {
            $setting = StoreSetting::query()->where('key', $key)->firstOrFail();
            $this->assertSame($expectedEditor, StoreSettingResource::editorKind($setting));
        }
    }

    public function test_all_legacy_resources_are_hidden_without_deleting_their_data(): void
    {
        foreach ([
            ProductResource::class,
            CategoryResource::class,
            BlogPostResource::class,
            FaqResource::class,
            ReviewResource::class,
            SettingResource::class,
            SitePageResource::class,
            SliderResource::class,
            BrandResource::class,
        ] as $resource) {
            $this->assertFalse($resource::shouldRegisterNavigation(), $resource.' must stay hidden from the operator menu.');
        }
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
