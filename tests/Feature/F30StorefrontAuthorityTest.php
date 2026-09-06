<?php

namespace Tests\Feature;

use App\Filament\Resources\BakeryCategoryLandingResource;
use App\Filament\Resources\StoreSettingResource;
use App\Models\BakeryCategoryLanding;
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
            'category_guide.title',
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

    public function test_category_seo_baseline_is_preserved_exactly_in_backend_landings(): void
    {
        $this->assertSame(7, BakeryCategoryLanding::query()->count());

        $expected = [
            'cookies' => [
                'catalog_category_slug' => 'kokyhay-khangy',
                'catalog_search' => null,
                'meta_title' => 'خرید کوکی خانگی | انواع کوکی وینیمی',
                'meta_description' => 'مشاهده و خرید آنلاین انواع کوکی وینیمی؛ مقایسه طعم‌ها، وزن‌ها، قیمت، موجودی و شرایط نگهداری هر محصول.',
                'heading' => 'کوکی‌های وینیمی؛ انتخاب بر اساس طعم و حال‌وهوا',
            ],
            'mini-cookies' => [
                'catalog_category_slug' => 'myny-koky',
                'catalog_search' => null,
                'meta_title' => 'خرید مینی کوکی | مینی کوکی برای پذیرایی و هدیه',
            ],
            'diet-diabetic' => [
                'catalog_category_slug' => 'rzhymy-o-bdon-knd-afzodh',
                'catalog_search' => null,
                'meta_title' => 'کوکی رژیمی و بدون قند افزوده | وینیمی',
            ],
            'cakes' => [
                'catalog_category_slug' => 'kyk-o-dsr',
                'catalog_search' => null,
                'meta_title' => 'خرید کیک و دسر خانگی | وینیمی بیکری',
            ],
            'cheesecakes' => [
                'catalog_category_slug' => 'kyk-o-dsr',
                'catalog_search' => 'چیزکیک',
                'meta_title' => 'خرید چیزکیک | انواع چیزکیک وینیمی',
            ],
            'pastry' => [
                'catalog_category_slug' => 'rol-o-krosan',
                'catalog_search' => null,
                'meta_title' => 'خرید رول و کروسان | محصولات خمیری وینیمی',
            ],
            'gift-boxes' => [
                'catalog_category_slug' => 'gift',
                'catalog_search' => null,
                'meta_title' => 'باکس هدیه کوکی و شیرینی | وینیمی',
            ],
        ];

        foreach ($expected as $slug => $attributes) {
            $this->assertDatabaseHas('bakery_category_landings', [
                'public_slug' => $slug,
                ...$attributes,
            ]);
        }

        $cookies = BakeryCategoryLanding::query()->where('public_slug', 'cookies')->firstOrFail();
        $this->assertSame('چطور کوکی مناسب را انتخاب کنم؟', $cookies->sections[0]['title']);
        $this->assertSame('قیمت و موجودی کوکی‌ها کجا مشخص می‌شود؟', $cookies->faq[0]['question']);
    }

    public function test_f29s_internal_linking_baseline_is_preserved_and_backend_managed(): void
    {
        $cookies = BakeryCategoryLanding::query()->where('public_slug', 'cookies')->firstOrFail();
        $miniCookies = BakeryCategoryLanding::query()->where('public_slug', 'mini-cookies')->firstOrFail();
        $cakes = BakeryCategoryLanding::query()->where('public_slug', 'cakes')->firstOrFail();

        $this->assertSame('/blog/cookie-storage-guide', $cookies->guides[0]['href']);
        $this->assertSame('/blog/cookies-per-guest-guide', $cookies->guides[1]['href']);
        $this->assertSame('/blog/cookies-per-guest-guide', $miniCookies->guides[0]['href']);
        $this->assertSame('/blog/cheesecake-cold-storage', $cakes->guides[0]['href']);
    }

    public function test_catalog_api_exposes_managed_category_landing_contract_without_replacing_category_data(): void
    {
        $this->getJson('/api/catalog/categories')
            ->assertOk()
            ->assertJsonCount(7, 'meta.categoryLandings')
            ->assertJsonPath('meta.categoryLandings.0.slug', 'cookies')
            ->assertJsonPath('meta.categoryLandings.0.catalogCategorySlug', 'kokyhay-khangy')
            ->assertJsonPath('meta.categoryLandings.0.seo.title', 'خرید کوکی خانگی | انواع کوکی وینیمی')
            ->assertJsonPath('meta.categoryLandings.4.slug', 'cheesecakes')
            ->assertJsonPath('meta.categoryLandings.4.catalogSearch', 'چیزکیک');
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

    public function test_category_landing_filament_resource_exposes_seo_and_internal_link_control(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/BakeryCategoryLandingResource.php'));

        $this->assertStringContainsString("TextInput::make('meta_title')", $source);
        $this->assertStringContainsString("Textarea::make('meta_description')", $source);
        $this->assertStringContainsString("TextInput::make('heading')", $source);
        $this->assertStringContainsString("Repeater::make('sections')", $source);
        $this->assertStringContainsString("Repeater::make('faq')", $source);
        $this->assertStringContainsString("Repeater::make('guides')", $source);
        $this->assertSame(BakeryCategoryLanding::class, BakeryCategoryLandingResource::getModel());
    }
}
