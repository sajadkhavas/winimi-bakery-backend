<?php

namespace Tests\Feature;

use App\Filament\Resources\StoreSettingResource;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class F30StorefrontAuthorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_editable_surfaces_are_seeded_as_public_backend_authority(): void
    {
        $requiredKeys = [
            'brand.name',
            'contact.phone',
            'navigation.header_1_label',
            'header.context_line',
            'footer.support_title',
            'home.meta_title',
            'home.hero_title_line_1',
            'home.marquee_1',
            'home.occasion_1_title',
            'home.decision_path_1_title',
            'home.editorial_1_slug',
            'gift.hero_title',
            'gift.inquiry_title',
            'corporate.hero_title',
            'corporate.inquiry_title',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertDatabaseHas('store_settings', [
                'key' => $key,
                'is_public' => true,
            ]);
        }

        $this->assertGreaterThanOrEqual(190, StoreSetting::query()->where('is_public', true)->count());
    }

    public function test_public_api_exposes_nested_storefront_authority_and_admin_changes_flow_through(): void
    {
        StoreSetting::query()->where('key', 'home.hero_title_line_1')->update([
            'value' => 'عنوان قابل کنترل از بک‌اند',
        ]);
        StoreSetting::query()->where('key', 'navigation.header_2_label')->update([
            'value' => 'فروشگاه جدید',
        ]);

        $response = $this->getJson('/api/store/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.home.hero_title_line_1', 'عنوان قابل کنترل از بک‌اند')
            ->assertJsonPath('data.settings.navigation.header_2_label', 'فروشگاه جدید')
            ->assertJsonPath('data.settings.gift.inquiry_title', 'ثبت درخواست هدیه اختصاصی')
            ->assertJsonPath('data.settings.corporate.inquiry_title', 'ثبت درخواست سازمانی');

        $payload = $response->getContent();
        $this->assertStringNotContainsString('GOOGLE_CLIENT_SECRET', $payload);
        $this->assertStringNotContainsString('ZARINPAL_MERCHANT', $payload);
        $this->assertStringNotContainsString('KAVENEGAR_API', $payload);
    }

    public function test_home_decision_faq_content_is_managed_by_the_faq_domain(): void
    {
        $this->getJson('/api/store/faqs?category=home-decision')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.question', 'از کجا محصول مناسب را پیدا کنم؟');
    }

    public function test_filament_storefront_contract_cannot_be_created_or_deleted_from_the_panel(): void
    {
        $record = StoreSetting::query()->where('key', 'home.hero_title_line_1')->firstOrFail();

        $this->assertFalse(StoreSettingResource::canCreate());
        $this->assertFalse(StoreSettingResource::canDelete($record));

        $resourceSource = file_get_contents(app_path('Filament/Resources/StoreSettingResource.php'));
        $pageSource = file_get_contents(app_path('Filament/Resources/StoreSettingResource/Pages/ManageStoreSettings.php'));

        $this->assertStringContainsString("->disabled()", $resourceSource);
        $this->assertStringContainsString("->dehydrated(false)", $resourceSource);
        $this->assertStringNotContainsString('CreateAction::make()', $pageSource);
    }
}
