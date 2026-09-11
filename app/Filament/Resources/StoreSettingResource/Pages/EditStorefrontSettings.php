<?php

namespace App\Filament\Resources\StoreSettingResource\Pages;

use App\Filament\Resources\StoreSettingResource;
use App\Models\StoreSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditStorefrontSettings extends Page
{
    protected static string $resource = StoreSettingResource::class;

    protected static string $view = 'filament.pages.edit-storefront-settings';

    public ?array $data = [];

    #[Locked]
    public string $section = 'brand-contact';

    #[Locked]
    public array $originalValues = [];

    public const SECTIONS = [
        'brand-contact' => ['label' => 'برند و تماس', 'groups' => ['brand', 'contact', 'social']],
        'home' => ['label' => 'صفحه اصلی', 'groups' => ['home']],
        'navigation' => ['label' => 'هدر و فوتر', 'groups' => ['header', 'navigation', 'footer']],
        'pages' => ['label' => 'صفحات عمومی', 'groups' => ['blog_index', 'catalog', 'contact_page', 'faq_page', 'gift', 'corporate', 'gallery_page', 'locations_page', 'reviews_page', 'category_guide', 'managed_page_shell']],
        'commerce' => ['label' => 'فروش و ارسال', 'groups' => ['pricing', 'checkout', 'delivery', 'orders']],
        'trust-seo' => ['label' => 'اعتماد و سئو', 'groups' => ['trust', 'seo']],
        'pwa-launch' => ['label' => 'وب‌اپ و اتصال گوگل', 'groups' => ['pwa', 'app_ui', 'integrations', 'consent']],
    ];

    public function mount(): void
    {
        $this->authorizeOperator();
        $this->fillSection();
    }

    public function changeSection(string $section): void
    {
        $this->authorizeOperator();
        abort_unless(isset(self::SECTIONS[$section]), 404);
        $this->section = $section;
        $this->resetValidation();
        $this->fillSection();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(
                $this->settings()->map(
                    fn (StoreSetting $setting) => Forms\Components\Group::make([
                        StoreSettingResource::makeValueField($setting)->label($setting->label ?: 'تنظیم فروشگاه'),
                    ])->statePath('setting_'.$setting->getKey())
                )->all()
            )
            ->statePath('data')
            ->columns(2);
    }

    public function save(): void
    {
        $this->authorizeOperator();
        $values = $this->form->getState();

        DB::transaction(function () use ($values): void {
            // Only existing, server-selected settings may change. Never hydrate
            // key/type/group/is_public from browser state or seed missing rows.
            foreach ($this->settings(lock: true) as $setting) {
                $name = 'setting_'.$setting->getKey();
                if (! array_key_exists($name, $values) || ! array_key_exists($name, $this->originalValues)) {
                    continue;
                }

                $value = $this->valueForStorage($setting, $values[$name]['value'] ?? null);
                if ((string) $value === (string) $this->originalValues[$name]) {
                    continue;
                }

                if ((string) $setting->value !== (string) $this->originalValues[$name]) {
                    throw ValidationException::withMessages([
                        "data.{$name}.value" => 'این مقدار توسط مدیر دیگری تغییر کرده است؛ بخش را دوباره باز کنید.',
                    ]);
                }

                abort_unless(StoreSettingResource::canEdit($setting), 403);
                $setting->update(['value' => $value]);
            }
        });

        $this->fillSection();
        Notification::make()->title('تنظیمات این بخش ذخیره شد')->success()->send();
    }

    private function fillSection(): void
    {
        $values = [];
        $this->originalValues = [];

        foreach ($this->settings() as $setting) {
            $name = 'setting_'.$setting->getKey();
            $value = $setting->value;

            // Repeaters and TagsInput require array state before Filament builds
            // child containers. These settings remain JSON text in storage.
            if (in_array(StoreSettingResource::editorKind($setting), ['shortcuts', 'slug-list'], true)) {
                $decoded = json_decode((string) $value, true);
                $value = is_array($decoded) ? array_values($decoded) : [];
            }

            $values[$name] = ['value' => $value];
            // Keep the raw stored value for optimistic concurrency checks and
            // to preserve the database contract used by the storefront API.
            $this->originalValues[$name] = $setting->value;
        }

        $this->form->fill($values);
    }

    private function valueForStorage(StoreSetting $setting, mixed $value): mixed
    {
        $kind = StoreSettingResource::editorKind($setting);

        if ($kind === 'shortcuts') {
            return json_encode(
                array_values(is_array($value) ? $value : []),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) ?: '[]';
        }

        if ($kind === 'slug-list') {
            $slugs = array_values(array_unique(array_filter(
                array_map(
                    fn ($slug): string => strtolower(trim((string) $slug)),
                    is_array($value) ? $value : [],
                ),
                fn (string $slug): bool => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1,
            )));

            return json_encode($slugs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return $value;
    }

    private function settings(bool $lock = false): Collection
    {
        return StoreSetting::query()
            ->whereIn('group', self::SECTIONS[$this->section]['groups'] ?? [])
            ->where('key', '!=', 'social.instagram')
            ->orderBy('group')
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    private function authorizeOperator(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'panel_user']) && StoreSettingResource::canViewAny(), 403);
    }
}
