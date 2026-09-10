<?php

namespace Tests\Feature;

use App\Filament\Resources\BakeryFaqResource;
use App\Filament\Resources\CustomerResource;
use App\Models\BakeryFaq;
use App\Models\BakeryProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostHandoffOperatorUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_mobile_is_masked_and_account_state_is_action_owned(): void
    {
        $this->assertSame('0912•••••67', CustomerResource::maskMobile('09123456767'));
        $this->assertSame('—', CustomerResource::maskMobile(null));

        $source = file_get_contents(app_path('Filament/Resources/CustomerResource.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString('->copyable()', $source);
        $this->assertMatchesRegularExpression(
            "/Toggle::make\('is_active'\).*?->disabled\(\).*?->dehydrated\(false\)/s",
            $source,
        );
        $this->assertMatchesRegularExpression(
            "/Toggle::make\('marketing_consent'\).*?->disabled\(\).*?->dehydrated\(false\)/s",
            $source,
        );
    }

    public function test_faq_categories_are_structured_without_losing_existing_values(): void
    {
        BakeryFaq::query()->create([
            'category' => 'custom-existing',
            'sort_order' => 0,
            'is_active' => true,
            'question' => 'سؤال موجود',
            'answer' => '<p>پاسخ موجود</p>',
        ]);

        $options = BakeryFaqResource::categoryOptions();

        $this->assertSame('عمومی', $options['general']);
        $this->assertSame('راهنمای انتخاب در صفحه اصلی', $options['home-decision']);
        $this->assertSame('custom-existing', $options['custom-existing']);

        $source = file_get_contents(app_path('Filament/Resources/BakeryFaqResource.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString("Select::make('category')", $source);
        $this->assertStringContainsString("->reorderable('sort_order')", $source);
        $this->assertStringContainsString('->bulkActions([])', $source);
    }

    public function test_delivery_zone_form_enforces_non_negative_and_coherent_preparation_window(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/DeliveryZoneResource.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("->rules(['lte:preparation_max_days'])", $source);
        $this->assertStringContainsString("->rules(['gte:preparation_min_days'])", $source);
        $this->assertStringContainsString('->bulkActions([])', $source);
        $this->assertStringContainsString('DeleteAction::make()', $source);
    }

    public function test_product_variant_weight_range_has_a_public_label_and_safe_admin_contract(): void
    {
        $range = new BakeryProductVariant([
            'weight_min_grams' => 175,
            'weight_max_grams' => 200,
        ]);
        $exact = new BakeryProductVariant(['weight_grams' => 180]);

        $this->assertSame('175 تا 200 گرم', $range->weightLabel());
        $this->assertSame('180 گرم', $exact->weightLabel());

        $resource = file_get_contents(app_path('Filament/Resources/BakeryProductResource.php'));
        $this->assertIsString($resource);
        $this->assertStringContainsString("TextInput::make('weight_min_grams')", $resource);
        $this->assertStringContainsString("TextInput::make('weight_max_grams')", $resource);
        $this->assertStringContainsString("TiptapEditor::make('description')", $resource);
        $this->assertStringNotContainsString("RichEditor::make('description')", $resource);
        $this->assertStringContainsString('->output(TiptapOutput::Html)', $resource);
        $this->assertMatchesRegularExpression(
            "/TextInput::make\('preparation_time_days'\).*?->disabled\(\).*?->dehydrated\(false\)/s",
            $resource,
        );

        $apiResource = file_get_contents(app_path('Http/Resources/BakeryVariantResource.php'));
        $this->assertIsString($apiResource);
        $this->assertStringContainsString("'weightMinGrams' => \$this->weight_min_grams", $apiResource);
        $this->assertStringContainsString("'weightMaxGrams' => \$this->weight_max_grams", $apiResource);
        $this->assertStringContainsString("'weight' => \$this->weightLabel()", $apiResource);
    }
}
