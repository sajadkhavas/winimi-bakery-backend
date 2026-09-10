<?php

namespace Tests\Feature;

use App\Filament\Resources\StoreSettingResource\Pages\EditStorefrontSettings;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\ManagedHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAudit32CompletionTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Role::findOrCreate('panel_user', 'web');
        $user = User::factory()->create();
        $user->assignRole('panel_user');

        return $user;
    }

    public function test_grouped_settings_save_values_without_changing_contract_or_other_sections(): void
    {
        $this->actingAs($this->operator());
        $first = StoreSetting::query()->where('key', 'contact.instagram_url')->firstOrFail();
        $other = StoreSetting::query()->where('key', 'home.hero_image_url')->firstOrFail();
        $contract = $first->only(['key', 'type', 'group', 'is_public']);
        $otherValue = $other->value;

        Livewire::test(EditStorefrontSettings::class)
            ->set('data.setting_'.$first->id.'.value', 'https://www.instagram.com/winimi_test')
            ->set('data.setting_'.$first->id.'.key', 'attacker.key')
            ->set('data.setting_'.$other->id.'.value', 'https://example.test/changed.jpg')
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('https://www.instagram.com/winimi_test', $first->fresh()->value);
        $this->assertSame($contract, $first->fresh()->only(array_keys($contract)));
        $this->assertSame($otherValue, $other->fresh()->value);
        $this->assertDatabaseMissing('store_settings', ['key' => 'attacker.key']);
    }

    public function test_grouped_settings_refuse_to_overwrite_another_operators_edit(): void
    {
        $this->actingAs($this->operator());
        $setting = StoreSetting::query()->where('key', 'contact.instagram_url')->firstOrFail();
        $page = Livewire::test(EditStorefrontSettings::class);
        $setting->update(['value' => 'https://instagram.com/other_operator']);

        $page->set('data.setting_'.$setting->id.'.value', 'https://instagram.com/stale_edit')
            ->call('save')->assertHasErrors(['data.setting_'.$setting->id.'.value']);

        $this->assertSame('https://instagram.com/other_operator', $setting->fresh()->value);
    }

    public function test_grouped_settings_require_an_operator_role(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(EditStorefrontSettings::class)->assertForbidden();
    }

    public function test_unknown_wrappers_cannot_bypass_nested_html_sanitization(): void
    {
        $html = ManagedHtmlSanitizer::sanitize('<unknown><script>alert(1)</script><img src="/image.webp" onerror="alert(2)"><a href="javascript:alert(3)">لینک</a><p>متن امن</p></unknown>');
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('متن امن', $html);
        $this->assertStringContainsString('/image.webp', $html);
    }
}
