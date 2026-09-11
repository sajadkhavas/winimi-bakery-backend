<?php

namespace Tests\Feature;

use App\Filament\Resources\StoreSettingResource\Pages\EditStorefrontSettings;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PostLaunchAdminRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_and_google_section_can_render_and_hydrate_without_server_error(): void
    {
        $this->actingAs($this->operator());
        $this->seedPwaLaunchSettings();

        $before = StoreSetting::query()
            ->whereIn('group', ['pwa', 'app_ui', 'integrations', 'consent'])
            ->pluck('value', 'key')
            ->all();

        $component = Livewire::test(EditStorefrontSettings::class)
            ->call('changeSection', 'pwa-launch')
            ->assertSet('section', 'pwa-launch')
            ->assertHasNoErrors();

        $shortcuts = StoreSetting::query()->where('key', 'pwa.shortcuts')->firstOrFail();
        $shortcutState = $component->get('data.setting_'.$shortcuts->getKey().'.value');

        $this->assertIsArray($shortcutState);
        $firstShortcut = array_values($shortcutState)[0] ?? null;
        $this->assertIsArray($firstShortcut);
        $this->assertSame('فروشگاه', $firstShortcut['name'] ?? null);
        $this->assertSame('/products', $firstShortcut['url'] ?? null);

        $after = StoreSetting::query()
            ->whereIn('group', ['pwa', 'app_ui', 'integrations', 'consent'])
            ->pluck('value', 'key')
            ->all();

        $this->assertSame($before, $after, 'Hydrating the admin form must not mutate persisted settings.');
    }

    public function test_pwa_shortcuts_with_malformed_json_fail_closed_without_mutating_storage(): void
    {
        $this->actingAs($this->operator());
        $this->seedPwaLaunchSettings();

        $shortcuts = StoreSetting::query()->where('key', 'pwa.shortcuts')->firstOrFail();
        $shortcuts->update(['value' => '{malformed-json']);

        $component = Livewire::test(EditStorefrontSettings::class)
            ->call('changeSection', 'pwa-launch')
            ->assertSet('section', 'pwa-launch')
            ->assertHasNoErrors();

        $shortcutState = $component->get('data.setting_'.$shortcuts->getKey().'.value');

        $this->assertIsArray($shortcutState);
        $this->assertSame([], array_values($shortcutState));
        $this->assertSame('{malformed-json', $shortcuts->fresh()->value);
    }

    public function test_pwa_launch_section_save_preserves_other_values_and_never_creates_browser_supplied_keys(): void
    {
        $this->actingAs($this->operator());
        $this->seedPwaLaunchSettings();

        $theme = StoreSetting::query()->where('key', 'pwa.theme_color')->firstOrFail();
        $shortcuts = StoreSetting::query()->where('key', 'pwa.shortcuts')->firstOrFail();
        $googleMode = StoreSetting::query()->where('key', 'integrations.google_tag_mode')->firstOrFail();
        $googleId = StoreSetting::query()->where('key', 'integrations.google_tag_id')->firstOrFail();

        $originalShortcuts = $shortcuts->value;
        $originalGoogleMode = $googleMode->value;
        $originalGoogleId = $googleId->value;

        Livewire::test(EditStorefrontSettings::class)
            ->call('changeSection', 'pwa-launch')
            ->set('data.setting_'.$theme->getKey().'.value', '#AABBCC')
            ->set('data.setting_'.$theme->getKey().'.key', 'attacker.injected')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('#AABBCC', $theme->fresh()->value);
        $this->assertSame($originalShortcuts, $shortcuts->fresh()->value);
        $this->assertSame($originalGoogleMode, $googleMode->fresh()->value);
        $this->assertSame($originalGoogleId, $googleId->fresh()->value);
        $this->assertDatabaseMissing('store_settings', ['key' => 'attacker.injected']);
    }

    private function operator(): User
    {
        Role::findOrCreate('panel_user', 'web');

        $user = User::query()->create([
            'name' => 'Post-launch Admin Runtime',
            'email' => 'post-launch-admin@example.test',
            'password' => 'post-launch-test-password',
        ]);
        $user->assignRole('panel_user');

        return $user;
    }

    private function seedPwaLaunchSettings(): void
    {
        $settings = [
            ['group' => 'pwa', 'key' => 'pwa.shortcuts', 'type' => 'json', 'value' => '[{"name":"فروشگاه","short_name":"فروشگاه","description":"محصولات","url":"/products"}]', 'label' => 'میانبرهای وب‌اپ', 'is_public' => true],
            ['group' => 'pwa', 'key' => 'pwa.theme_color', 'type' => 'string', 'value' => '#D0E596', 'label' => 'رنگ اصلی وب‌اپ', 'is_public' => true],
            ['group' => 'pwa', 'key' => 'pwa.background_color', 'type' => 'string', 'value' => '#FFFDF7', 'label' => 'رنگ پس‌زمینه وب‌اپ', 'is_public' => true],
            ['group' => 'app_ui', 'key' => 'app_ui.install_prompt_enabled', 'type' => 'boolean', 'value' => '1', 'label' => 'نمایش نصب وب‌اپ', 'is_public' => true],
            ['group' => 'integrations', 'key' => 'integrations.google_tag_mode', 'type' => 'string', 'value' => 'none', 'label' => 'روش اتصال Google Tag', 'is_public' => true],
            ['group' => 'integrations', 'key' => 'integrations.google_tag_id', 'type' => 'string', 'value' => '', 'label' => 'شناسه Google Tag', 'is_public' => true],
            ['group' => 'integrations', 'key' => 'integrations.search_console_verification', 'type' => 'string', 'value' => '', 'label' => 'کد Search Console', 'is_public' => true],
            ['group' => 'consent', 'key' => 'consent.analytics_enabled', 'type' => 'boolean', 'value' => '0', 'label' => 'رضایت تحلیل', 'is_public' => true],
        ];

        foreach ($settings as $setting) {
            StoreSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
