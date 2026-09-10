<?php

namespace Tests\Feature;

use App\Filament\Pages\CacheManagerPage;
use App\Filament\Pages\FileManagerPage;
use App\Filament\Pages\QueueMonitorPage;
use App\Filament\Resources\StoreSettingResource\Pages\EditStorefrontSettings;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\ManagedHtmlSanitizer;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAudit32CompletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Audit Operator',
            'email' => $email,
            'password' => 'audit-test-password',
        ]);
    }

    private function operator(): User
    {
        Role::findOrCreate('panel_user', 'web');
        $user = $this->makeUser('audit-operator@example.test');
        $user->assignRole('panel_user');

        return $user;
    }

    private function superAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = $this->makeUser('audit-super-admin@example.test');
        $user->assignRole('super_admin');

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
        $this->actingAs($this->makeUser('audit-no-role@example.test'));
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

    public function test_native_callout_contract_survives_sanitization_but_arbitrary_classes_do_not(): void
    {
        $callout = ManagedHtmlSanitizer::sanitize('<div class="filament-tiptap-hurdle" data-color="primary" onclick="alert(1)"><p>نکته مهم</p></div>');
        $this->assertStringContainsString('class="filament-tiptap-hurdle"', $callout);
        $this->assertStringContainsString('data-color="primary"', $callout);
        $this->assertStringNotContainsString('onclick', $callout);

        $unsafe = ManagedHtmlSanitizer::sanitize('<div class="arbitrary-class" data-color="primary"><p>متن</p></div>');
        $this->assertStringNotContainsString('arbitrary-class', $unsafe);
        $this->assertStringNotContainsString('data-color', $unsafe);
    }

    public function test_managed_editor_exposes_native_callout_preview_and_persian_labels(): void
    {
        $this->assertContains('hurdle', config('filament-tiptap-editor.profiles.default'));

        $actions = TiptapEditor::make('content')->getHintActions();
        $this->assertArrayHasKey('bakery_tiptap_preview', $actions);
        $this->assertSame('پیش‌نمایش', $actions['bakery_tiptap_preview']->getLabel());

        $this->assertSame('باکس نکته', trans('filament-tiptap-editor::editor.hurdle.label'));
        $this->assertSame('بازانجام', trans('filament-tiptap-editor::editor.redo'));
        $this->assertSame('تأکیدی', trans('filament-tiptap-editor::editor.hurdle.colors.accent'));
    }

    public function test_developer_tools_are_super_admin_only(): void
    {
        $this->actingAs($this->operator());
        $this->assertFalse(QueueMonitorPage::canAccess());
        $this->assertFalse(CacheManagerPage::canAccess());
        $this->assertFalse(FileManagerPage::canAccess());

        $this->actingAs($this->superAdmin());
        $this->assertTrue(QueueMonitorPage::canAccess());
        $this->assertTrue(CacheManagerPage::canAccess());
        $this->assertTrue(FileManagerPage::canAccess());
    }

    public function test_local_admin_navigation_uses_only_the_six_audit_groups(): void
    {
        $allowed = [
            'فروشگاه',
            'محتوا',
            'بازاریابی و سئو',
            'ارتباطات',
            'تنظیمات فروشگاه',
            'سیستم و امنیت',
        ];

        $paths = array_map(
            static fn ($file): string => $file->getPathname(),
            File::allFiles(app_path('Filament')),
        );
        $paths[] = app_path('Providers/Filament/AdminPanelProvider.php');

        foreach ($paths as $path) {
            $source = File::get($path);
            preg_match_all(
                "/(?:\\$navigationGroup\\s*=\\s*|->navigationGroup\\()'([^']+)'/",
                $source,
                $matches,
            );

            foreach ($matches[1] ?? [] as $group) {
                $this->assertContains($group, $allowed, "Unexpected navigation group [{$group}] in {$path}");
            }
        }
    }
}
