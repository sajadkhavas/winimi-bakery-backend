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

    protected static ?string $navigationLabel = 'تنظیمات فروشگاه';

    protected static ?string $modelLabel = 'تنظیم';

    protected static ?string $pluralModelLabel = 'تنظیمات فروشگاه';

    protected static ?string $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 1;

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
                    Forms\Components\Textarea::make('value')
                        ->label('مقدار')
                        ->rows(5)
                        ->helperText(fn (?StoreSetting $record): string => match ($record?->type) {
                            'boolean' => 'برای گزینه بله/خیر فقط 1 (فعال) یا 0 (غیرفعال) وارد کنید.',
                            'integer' => 'فقط عدد صحیح وارد کنید.',
                            'json' => 'JSON معتبر وارد کنید. این نوع فقط برای تنظیمات ساختاری ثبت‌شده استفاده می‌شود.',
                            default => str_contains((string) $record?->key, '_href')
                                ? 'برای لینک‌های داخلی مسیر با / شروع شود. لینک‌های خارجی فقط در فیلدهای مشخص‌شده استفاده می‌شوند.'
                                : (str_contains((string) $record?->key, '_image_url')
                                    ? 'URL تصویر را وارد کنید؛ خالی بماند تا تصویر پیش‌فرض Frontend استفاده شود.'
                                    : 'متن قابل نمایش در سایت را وارد کنید.'),
                        })
                        ->rules(fn (?StoreSetting $record): array => match ($record?->type) {
                            'boolean' => ['nullable', 'in:0,1'],
                            'integer' => ['nullable', 'integer'],
                            'json' => ['nullable', 'json'],
                            default => ['nullable', 'string'],
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
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
