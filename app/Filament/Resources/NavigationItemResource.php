<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\BakeryCategory;
use App\Models\NavigationItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = 'منوهای هدر و فوتر';

    protected static ?string $modelLabel = 'آیتم منو';

    protected static ?string $pluralModelLabel = 'منوهای هدر و فوتر';

    protected static ?string $navigationGroup = 'تنظیمات فروشگاه';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('لینک و جایگاه منو')
                ->description('برای هدر و فوتر می‌توانید گروه اصلی و زیرلینک بسازید. در فوتر، آیتم اصلیِ دارای زیرمجموعه عنوان ستون می‌شود و زیرمجموعه‌ها لینک‌های همان ستون هستند.')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('عنوان')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('href')
                        ->label('مسیر / لینک')
                        ->required()
                        ->maxLength(255)
                        ->helperText('برای لینک داخلی با / شروع کنید؛ مثل /products یا /about.'),

                    Forms\Components\Select::make('parent_id')
                        ->label('زیرمجموعه‌ی')
                        ->options(fn (): array => NavigationItem::query()
                            ->whereNull('parent_id')
                            ->orderBy('sort_order')
                            ->pluck('label', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->placeholder('گروه / منوی اصلی')
                        ->helperText('برای ساخت ستون فوتر، عنوان ستون را بدون والد بسازید و لینک‌های آن را زیرمجموعه قرار دهید.'),

                    Forms\Components\Select::make('linked_category_id')
                        ->label('دسته محصول مرتبط')
                        ->options(fn (): array => BakeryCategory::query()
                            ->orderBy('sort_order')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('اختیاری؛ برای اتصال معنایی منو به یک دسته و امکان مخفی‌سازی در صورت خالی بودن.'),

                    Forms\Components\Select::make('placement')
                        ->label('محل نمایش')
                        ->options([
                            'all' => 'هدر دسکتاپ + موبایل',
                            'header' => 'فقط هدر دسکتاپ',
                            'mobile' => 'فقط منوی موبایل',
                            'footer' => 'فقط فوتر',
                        ])
                        ->default('all')
                        ->required()
                        ->helperText('برای ستون‌ها و لینک‌های فوتر، «فقط فوتر» را انتخاب کنید.'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('ترتیب نمایش')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),

                    Forms\Components\TextInput::make('icon')
                        ->label('آیکون (اختیاری)')
                        ->maxLength(100),

                    Forms\Components\Textarea::make('description')
                        ->label('توضیح کوتاه (اختیاری)')
                        ->rows(2),

                    Forms\Components\FileUpload::make('image_path')
                        ->label('تصویر منو (اختیاری)')
                        ->image()
                        ->directory('navigation'),

                    Forms\Components\Toggle::make('open_in_new_tab')
                        ->label('بازشدن در تب جدید'),

                    Forms\Components\Toggle::make('hide_when_empty')
                        ->label('مخفی‌کردن دسته بدون محصول')
                        ->helperText('فقط وقتی دسته محصول مرتبط انتخاب شده باشد.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('عنوان')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('href')
                    ->label('مسیر')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('parent.label')
                    ->label('والد / ستون')
                    ->default('اصلی'),

                Tables\Columns\TextColumn::make('placement')
                    ->label('محل نمایش')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'header' => 'هدر دسکتاپ',
                        'mobile' => 'موبایل',
                        'footer' => 'فوتر',
                        default => 'هدر + موبایل',
                    }),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('placement')
                    ->label('محل نمایش')
                    ->options([
                        'all' => 'هدر + موبایل',
                        'header' => 'هدر دسکتاپ',
                        'mobile' => 'موبایل',
                        'footer' => 'فوتر',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('فعال'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavigationItems::route('/'),
            'create' => Pages\CreateNavigationItem::route('/create'),
            'edit' => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }
}
