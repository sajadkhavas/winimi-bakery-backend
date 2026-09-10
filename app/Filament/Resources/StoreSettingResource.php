<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreSettingResource\Pages;
use App\Models\StoreSetting;
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

    protected static ?string $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('محتوای قابل مدیریت')
                ->description('کلید، نوع داده و وضعیت عمومی بخشی از قرارداد Frontend/API هستند و از پنل قابل تغییر نیستند. فقط مقدار محتوایی را ویرایش کنید.')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('عنوان')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('group')
                        ->label('گروه')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('key')
                        ->label('کلید فنی')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('type')
                        ->label('نوع داده')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\Toggle::make('is_public')
                        ->label('قابل نمایش در API عمومی')
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

    private static function makeValueField(?StoreSetting $record): Forms\Components\Field
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
                        ->helperText('مسیر با / شروع شود و آدرس خارجی وارد نشود.')
                        ->rules(['string', 'max:255', 'regex:/^\/(?!\/).*$/']),
                ])
                ->columns(2)
                ->maxItems(4)
                ->addActionLabel('افزودن میانبر')
                ->reorderable(),
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
                ->helperText('مثل /products یا /blog؛ آدرس خارجی مجاز نیست.')
                ->maxLength(255)
                ->rules(['nullable', 'regex:/^\/(?!\/).*$/']),
            'media-url' => Forms\Components\TextInput::make('value')
                ->label('آدرس تصویر')
                ->helperText('مسیر داخلی /... یا URL کامل http/https مجاز است.')
                ->maxLength(2048)
                ->rules(['nullable', 'regex:/^(\/(?!\/).+|https?:\/\/\S+)$/i']),
            'url' => Forms\Components\TextInput::make('value')
                ->label('نشانی اینترنتی')
                ->url()
                ->maxLength(2048),
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
                Tables\Columns\TextColumn::make('group')->label('گروه')->badge()->sortable(),
                Tables\Columns\TextColumn::make('label')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('key')->label('کلید')->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('type')->label('نوع')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('is_public')->label('عمومی')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('گروه')
                    ->options(fn (): array => StoreSetting::query()->distinct()->orderBy('group')->pluck('group', 'group')->all()),
                Tables\Filters\TernaryFilter::make('is_public')->label('عمومی'),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('ویرایش مقدار')])
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
            'index' => Pages\ManageStoreSettings::route('/'),
        ];
    }
}
