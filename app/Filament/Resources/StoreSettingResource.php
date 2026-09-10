<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreSettingResource\Pages;
use App\Models\BakeryPost;
use App\Models\StoreSetting;
use App\Support\AdminMediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreSettingResource extends Resource
{
    protected static ?string $model = StoreSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'محتوای سایت و صفحه اصلی';

    protected static ?string $modelLabel = 'تنظیم';

    protected static ?string $pluralModelLabel = 'محتوای سایت و صفحه اصلی';

    protected static ?string $navigationGroup = 'تنظیمات فروشگاه';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('ویرایش محتوای سایت')
                ->description('فقط مقدار قابل‌نمایش برای کارفرما ویرایش می‌شود. کلید فنی، نوع داده و قرارداد API پنهان و محافظت‌شده باقی می‌مانند.')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('عنوان')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('group')
                        ->label('بخش')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\Group::make()
                        ->schema(fn (?StoreSetting $record): array => [self::makeValueField($record)])
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function editorKind(?StoreSetting $record): string
    {
        if (! $record) {
            return 'text';
        }

        if ($record->key === 'pwa.shortcuts') {
            return 'shortcuts';
        }

        if (in_array($record->key, ['pwa.theme_color', 'pwa.background_color'], true)) {
            return 'color';
        }

        if ($record->key === 'integrations.google_tag_mode') {
            return 'tag-mode';
        }

        if ($record->key === 'integrations.google_tag_id') {
            return 'tag-id';
        }

        if ($record->key === 'integrations.search_console_verification') {
            return 'search-console';
        }

        if ($record->key === 'pricing.cookie_bulk_discount.category_slugs') {
            return 'slug-list';
        }

        if ($record->key === 'trust.enamad_badge_code') {
            return 'enamad-badge';
        }

        if (in_array($record->key, [
            'home.editorial_1_slug',
            'home.editorial_2_slug',
            'home.editorial_3_slug',
        ], true)) {
            return 'post-slug';
        }

        if ($record->type === 'boolean') {
            return 'boolean';
        }

        if ($record->type === 'integer') {
            return 'integer';
        }

        if ($record->type === 'json') {
            return 'json';
        }

        if (str_contains($record->key, 'email')) {
            return 'email';
        }

        if (str_ends_with($record->key, '_href')) {
            return 'internal-path';
        }

        if (str_ends_with($record->key, '_image_url')) {
            return 'media-url';
        }

        if (str_contains($record->key, '_url')) {
            return 'url';
        }

        if (str_contains($record->key, 'phone')) {
            return 'phone';
        }

        if (str_contains($record->key, '_description')
            || str_contains($record->key, '_text')
            || str_contains($record->key, '_copy')
            || str_contains($record->key, '_message')
            || str_contains($record->key, 'badge_code')) {
            return 'long-text';
        }

        return 'text';
    }

    public static function makeValueField(?StoreSetting $record): Forms\Components\Field
    {
        $kind = self::editorKind($record);

        return match ($kind) {
            'shortcuts' => Forms\Components\Repeater::make('value')
                ->label('میانبرهای اپ')
                ->formatStateUsing(function ($state): array {
                    if (is_array($state)) {
                        return array_values($state);
                    }

                    $decoded = json_decode((string) $state, true);

                    return is_array($decoded) ? array_values($decoded) : [];
                })
                ->dehydrateStateUsing(fn ($state): string => json_encode(
                    array_values(is_array($state) ? $state : []),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ) ?: '[]')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('نام')
                        ->required()
                        ->maxLength(80),
                    Forms\Components\TextInput::make('short_name')
                        ->label('نام کوتاه')
                        ->maxLength(40),
                    Forms\Components\TextInput::make('description')
                        ->label('توضیح')
                        ->maxLength(160)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('url')
                        ->label('مسیر داخلی')
                        ->required()
                        ->datalist(['/', '/products', '/blog', '/account', '/cart'])
                        ->helperText('مسیر با / شروع شود و آدرس خارجی وارد نشود.')
                        ->rules(['string', 'max:255', 'regex:/^\/(?!\/).*$/']),
                ])
                ->columns(2)
                ->maxItems(4)
                ->addActionLabel('افزودن میانبر')
                ->reorderable(),
            'slug-list' => Forms\Components\TagsInput::make('value')
                ->label('دسته‌های مشمول تخفیف عمده')
                ->helperText('فقط slug دسته‌های معتبر را وارد کنید؛ هر مورد جداگانه ثبت می‌شود و JSON لازم نیست.')
                ->formatStateUsing(function ($state): array {
                    if (is_array($state)) {
                        return array_values(array_filter($state, 'is_string'));
                    }

                    $decoded = json_decode((string) $state, true);

                    return is_array($decoded)
                        ? array_values(array_filter($decoded, 'is_string'))
                        : [];
                })
                ->dehydrateStateUsing(function ($state): string {
                    $values = array_values(array_unique(array_filter(
                        array_map(
                            fn ($value): string => strtolower(trim((string) $value)),
                            is_array($state) ? $state : [],
                        ),
                        fn (string $slug): bool => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1,
                    )));

                    return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
                })
                ->rules([
                    'array',
                    function ($attribute, $value, $fail): void {
                        if (! is_array($value)) {
                            $fail('فهرست دسته‌ها معتبر نیست.');

                            return;
                        }

                        foreach ($value as $slug) {
                            if (! is_string($slug) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', trim($slug)) !== 1) {
                                $fail('هر دسته باید یک slug انگلیسی معتبر باشد.');

                                return;
                            }
                        }
                    },
                ]),
            'enamad-badge' => Forms\Components\Textarea::make('value')
                ->label('کد رسمی نماد اعتماد الکترونیکی')
                ->rows(8)
                ->maxLength(20000)
                ->helperText('کد رسمی دریافت‌شده از eNAMAD را بدون تغییر وارد کنید. Frontend فقط ساختار امن با دامنه trustseal.enamad.ir را نمایش می‌دهد و هر کد نامعتبر را fail-closed رد می‌کند.'),
            'color' => Forms\Components\ColorPicker::make('value')
                ->label('رنگ')
                ->required()
                ->rules(['regex:/^#[0-9A-Fa-f]{6}$/']),
            'tag-mode' => Forms\Components\Select::make('value')
                ->label('روش اتصال Google Tag')
                ->options([
                    'none' => 'غیرفعال',
                    'gtag' => 'Google Analytics 4 (gtag)',
                    'gtm' => 'Google Tag Manager',
                ])
                ->required()
                ->native(false),
            'tag-id' => Forms\Components\TextInput::make('value')
                ->label('شناسه عمومی Google Tag')
                ->placeholder('G-XXXXXXXX یا GTM-XXXXXXX')
                ->helperText('فقط شناسه عمومی GA4/GTM؛ هیچ Secret یا Credential اینجا وارد نشود.')
                ->rules(['nullable', 'regex:/^(G-[A-Z0-9]+|GTM-[A-Z0-9]+)$/i']),
            'search-console' => Forms\Components\TextInput::make('value')
                ->label('کد تأیید Google Search Console')
                ->helperText('فقط token عمومی meta verification را وارد کنید؛ فایل، HTML یا Secret وارد نشود.')
                ->maxLength(255)
                ->rules(['nullable', 'regex:/^[A-Za-z0-9_-]+$/']),
            'post-slug' => Forms\Components\Select::make('value')
                ->label('مقاله منتشرشده')
                ->options(fn (): array => BakeryPost::query()
                    ->published()
                    ->orderByDesc('published_at')
                    ->pluck('title', 'slug')
                    ->all())
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('فقط مقاله‌ای که واقعاً منتشر شده انتخاب می‌شود؛ نیازی به واردکردن دستی slug نیست.'),
            'boolean' => Forms\Components\Toggle::make('value')
                ->label('فعال')
                ->formatStateUsing(fn ($state): bool => filter_var($state, FILTER_VALIDATE_BOOL))
                ->dehydrateStateUsing(fn ($state): string => $state ? '1' : '0'),
            'integer' => Forms\Components\TextInput::make('value')
                ->label('مقدار عددی')
                ->numeric()
                ->rules(['nullable', 'integer']),
            'json' => Forms\Components\Textarea::make('value')
                ->label('داده ساختاریافته JSON')
                ->rows(8)
                ->helperText('فقط JSON معتبر. برای ساختارهای ازپیش‌تعریف‌شده استفاده می‌شود.')
                ->rules(['nullable', 'json']),
            'email' => Forms\Components\TextInput::make('value')
                ->label('ایمیل')
                ->email()
                ->maxLength(255),
            'internal-path' => Forms\Components\TextInput::make('value')
                ->label('مسیر داخلی سایت')
                ->datalist(['/', '/products', '/blog', '/contact', '/about', '/cart', '/account'])
                ->helperText('یکی از مسیرهای پیشنهادی را انتخاب کنید یا مسیر داخلی معتبر با / وارد کنید؛ آدرس خارجی مجاز نیست.')
                ->maxLength(255)
                ->rules(['nullable', 'regex:/^\/(?!\/).*$/']),
            'media-url' => Forms\Components\Select::make('value')
                ->label('تصویر از کتابخانه رسانه')
                ->options(AdminMediaLibrary::imageUrlOptions($record?->value))
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('تصویر را از کتابخانه رسانه تخصصی وینیمی انتخاب کنید. مقدار قدیمی فعلی برای جلوگیری از حذف ناخواسته حفظ می‌شود.'),
            'url' => Forms\Components\TextInput::make('value')
                ->label('نشانی اینترنتی')
                ->url()
                ->maxLength(2048)
                ->helperText($record?->key === 'social.instagram'
                    ? 'این فیلد برای سازگاری قدیمی باقی مانده است؛ برای اطلاعات تماس اصلی از فیلد اینستاگرام در بخش برند و تماس استفاده کنید.'
                    : null),
            'phone' => Forms\Components\TextInput::make('value')
                ->label('شماره تماس')
                ->tel()
                ->maxLength(40),
            'long-text' => Forms\Components\Textarea::make('value')
                ->label('متن')
                ->rows(5)
                ->maxLength(5000),
            default => Forms\Components\TextInput::make('value')
                ->label('مقدار')
                ->maxLength(1000),
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->label('بخش')->badge()->sortable(),
                Tables\Columns\TextColumn::make('label')->label('عنوان')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y/m/d H:i')->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->label('کلید فنی')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('عمومی')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('بخش')
                    ->options(fn (): array => StoreSetting::query()->distinct()->orderBy('group')->pluck('group', 'group')->all()),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('ویرایش')])
            ->bulkActions([])
            ->defaultSort('group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\EditStorefrontSettings::route('/'),
            'records' => Pages\ManageStoreSettings::route('/records'),
        ];
    }
}
