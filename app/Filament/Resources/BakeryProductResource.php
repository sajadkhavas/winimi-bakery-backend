<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryProductResource\Pages;
use App\Models\BakeryProduct;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BakeryProductResource extends Resource
{
    protected static ?string $model = BakeryProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationLabel = 'محصولات بیکری';

    protected static ?string $modelLabel = 'محصول بیکری';

    protected static ?string $pluralModelLabel = 'محصولات بیکری';

    protected static ?string $navigationGroup = 'فروشگاه وینیمی';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('bakery-product')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('اطلاعات محصول')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Section::make()
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('نام محصول')
                                        ->required()
                                        ->maxLength(180)
                                        ->live(onBlur: true),
                                    Forms\Components\TextInput::make('product_code')
                                        ->label('کد محصول')
                                        ->required()
                                        ->maxLength(80)
                                        ->unique(ignoreRecord: true),
                                    Forms\Components\TextInput::make('slug')
                                        ->label('Slug')
                                        ->maxLength(200)
                                        ->helperText('در صورت خالی‌بودن از نام محصول ساخته می‌شود.'),
                                    Forms\Components\Select::make('category_id')
                                        ->label('دسته‌بندی')
                                        ->relationship('category', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Forms\Components\Textarea::make('short_description')
                                        ->label('توضیح کوتاه')
                                        ->rows(3)
                                        ->maxLength(320)
                                        ->columnSpanFull(),
                                    Forms\Components\RichEditor::make('description')
                                        ->label('توضیح کامل')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                            Forms\Components\Section::make('انتشار و تحویل')
                                ->schema([
                                    Forms\Components\Placeholder::make('launch_readiness')
                                        ->label('وضعیت انتشار')
                                        ->content(
                                            fn (?BakeryProduct $record): string => self::launchReadinessSummary($record)
                                        )
                                        ->columnSpanFull(),
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('فعال در فروشگاه')
                                        ->helperText(
                                            'این گزینه فقط یکی از شروط انتشار است. محصول فقط وقتی در فروشگاه دیده می‌شود که دسته فعال، حداقل یک Variant قابل‌فروش فعال و تأییدهای محتوا، رسانه و موجودی هم کامل باشند.'
                                        )
                                        ->default(false),
                                    Forms\Components\Toggle::make('is_featured')
                                        ->label('پیشنهاد وینیمی')
                                        ->default(false),
                                    Forms\Components\Toggle::make('requires_cooling')
                                        ->label('نیازمند نگهداری و ارسال سرد')
                                        ->helperText('مستقل از محدوده جغرافیایی ارسال است.')
                                        ->default(false),
                                    Forms\Components\Select::make('availability_mode')
                                        ->label('مدل تأمین محصول')
                                        ->options([
                                            BakeryProduct::AVAILABILITY_STOCKED => 'موجودی آماده',
                                            BakeryProduct::AVAILABILITY_MADE_TO_ORDER => 'آماده‌سازی پس از سفارش',
                                        ])
                                        ->placeholder('تعیین نشده')
                                        ->helperText('واقعیت تأمین محصول را مشخص می‌کند؛ موجودی را دور نمی‌زند.'),
                                    Forms\Components\TextInput::make('preparation_min_days')
                                        ->label('حداقل زمان آماده‌سازی (روز)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(30),
                                    Forms\Components\TextInput::make('preparation_max_days')
                                        ->label('حداکثر زمان آماده‌سازی (روز)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(30),
                                    Forms\Components\TextInput::make('preparation_time_days')
                                        ->label('زمان آماده‌سازی قدیمی / fallback')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(30)
                                        ->helperText('فقط برای سازگاری داده‌های قدیمی نگه داشته شده است؛ برای داده جدید بازه بالا را تکمیل کنید.'),
                                    Forms\Components\Select::make('shipping_scope')
                                        ->label('سیاست محدوده ارسال')
                                        ->options([
                                            BakeryProduct::SHIPPING_NATIONWIDE => 'ارسال سراسری مطابق تنظیمات تحویل',
                                            BakeryProduct::SHIPPING_CONFIGURED_ZONES => 'فقط محدوده‌های تنظیم‌شده فروشگاه',
                                            BakeryProduct::SHIPPING_PICKUP_ONLY => 'فقط تحویل حضوری',
                                        ])
                                        ->placeholder('تعیین نشده')
                                        ->helperText('این فیلد محدوده ارسال را مشخص می‌کند و از نیاز به ارسال سرد مستقل است.'),
                                    Forms\Components\Textarea::make('shipping_note')
                                        ->label('توضیح سیاست ارسال')
                                        ->rows(3)
                                        ->maxLength(1000)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('ترتیب نمایش')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0),
                                ])
                                ->columns(3),
                        ]),
                    Forms\Components\Tabs\Tab::make('قیمت و موجودی')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Forms\Components\Repeater::make('variants')
                                ->label('Variantهای قابل فروش')
                                ->relationship()
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('نام انتخاب')
                                        ->placeholder('مثلاً بسته ۶ عددی')
                                        ->required()
                                        ->maxLength(120),
                                    Forms\Components\TextInput::make('sku')
                                        ->label('SKU')
                                        ->required()
                                        ->maxLength(100)
                                        ->unique(ignoreRecord: true),
                                    Forms\Components\TextInput::make('weight_grams')
                                        ->label('وزن (گرم)')
                                        ->numeric()
                                        ->minValue(1),
                                    Forms\Components\TextInput::make('package_quantity')
                                        ->label('تعداد در بسته')
                                        ->numeric()
                                        ->minValue(1)
                                        ->helperText('اطلاعات بسته‌بندی است و به‌تنهایی ضریب تعداد سفارش نیست.'),
                                    Forms\Components\TextInput::make('min_order_quantity')
                                        ->label('حداقل تعداد سفارش')
                                        ->numeric()
                                        ->minValue(1)
                                        ->helperText('فقط در صورت تأیید واقعی فروشگاه مقداردهی شود.'),
                                    Forms\Components\TextInput::make('max_order_quantity')
                                        ->label('حداکثر تعداد سفارش')
                                        ->numeric()
                                        ->minValue(1)
                                        ->helperText('اگر سقف تجاری تأیید نشده است خالی بماند.'),
                                    Forms\Components\TextInput::make('regular_price_toman')
                                        ->label('قیمت عادی (تومان)')
                                        ->numeric()
                                        ->required()
                                        ->minValue(1),
                                    Forms\Components\TextInput::make('sale_price_toman')
                                        ->label('قیمت فروش (تومان)')
                                        ->numeric()
                                        ->minValue(1)
                                        ->lt('regular_price_toman'),
                                    Forms\Components\TextInput::make('stock_quantity')
                                        ->label('موجودی')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->default(0),
                                    Forms\Components\TextInput::make('low_stock_threshold')
                                        ->label('حد هشدار موجودی')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->default(5),
                                    Forms\Components\Toggle::make('inventory_verified')
                                        ->label('موجودی تأیید شده است')
                                        ->helperText('فقط پس از تأیید موجودی واقعی این Variant فعال شود.')
                                        ->default(false),
                                    Forms\Components\Toggle::make('is_default')
                                        ->label('انتخاب پیش‌فرض'),
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('قابل فروش')
                                        ->helperText(
                                            'Variant فعال وارد Gate انتشار می‌شود و موجودی واقعی آن باید تأیید شده باشد.'
                                        )
                                        ->default(true),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('ترتیب')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0),
                                ])
                                ->columns(3)
                                ->defaultItems(1)
                                ->minItems(1)
                                ->reorderableWithButtons()
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Variant جدید')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('ترکیبات و نگهداری')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->schema([
                            Forms\Components\TagsInput::make('ingredients')
                                ->label('ترکیبات')
                                ->separator(','),
                            Forms\Components\TagsInput::make('allergens')
                                ->label('آلرژن‌ها')
                                ->separator(','),
                            Forms\Components\TextInput::make('shelf_life')
                                ->label('ماندگاری')
                                ->maxLength(220),
                            Forms\Components\Textarea::make('storage_instructions')
                                ->label('روش نگهداری')
                                ->rows(4),
                            Forms\Components\Toggle::make('content_verified')
                                ->label('اطلاعات محصول تأیید شده است')
                                ->helperText('فقط بعد از بررسی ترکیبات، آلرژن، ماندگاری و نگهداری فعال شود.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('رسانه')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            SpatieMediaLibraryFileUpload::make('main_image')
                                ->label('تصویر اصلی')
                                ->collection('catalog-main')
                                ->image()
                                ->imageEditor()
                                ->acceptedFileTypes([
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                ])
                                ->maxSize(12 * 1024)
                                ->rules([
                                    'dimensions:max_width=6000,max_height=6000',
                                ])
                                ->helperText(
                                    'JPEG، PNG یا WebP؛ حداکثر ۱۲ مگابایت و ۶۰۰۰×۶۰۰۰ پیکسل. نسخه‌های بهینه خودکار ساخته می‌شوند.'
                                )
                                ->columnSpanFull(),
                            SpatieMediaLibraryFileUpload::make('gallery_images')
                                ->label('گالری')
                                ->collection('catalog-gallery')
                                ->multiple()
                                ->reorderable()
                                ->maxFiles(10)
                                ->image()
                                ->acceptedFileTypes([
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                ])
                                ->maxSize(12 * 1024)
                                ->rules([
                                    'dimensions:max_width=6000,max_height=6000',
                                ])
                                ->helperText(
                                    'برای هر تصویر: JPEG، PNG یا WebP؛ حداکثر ۱۲ مگابایت و ۶۰۰۰×۶۰۰۰ پیکسل.'
                                )
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('media_verified')
                                ->label('تصاویر متعلق به همین محصول و تأییدشده هستند')
                                ->helperText(
                                    'با افزودن یا حذف تصویر، تأیید رسانه خودکار باطل می‌شود و باید دوباره بررسی شود.'
                                )
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('سئو')
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Forms\Components\TextInput::make('meta_title')
                                ->label('عنوان سئو')
                                ->maxLength(70),
                            Forms\Components\Textarea::make('meta_description')
                                ->label('توضیح سئو')
                                ->maxLength(180)
                                ->rows(3),
                        ])
                        ->columns(2),
                ])
                ->persistTabInQueryString()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['category', 'variants'])
            )
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('main_image')
                    ->label('تصویر')
                    ->collection('catalog-main')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('نام محصول')
                    ->searchable()
                    ->sortable()
                    ->limit(45),
                Tables\Columns\TextColumn::make('product_code')
                    ->label('کد')
                    ->searchable(),
                Tables\Columns\TextColumn::make('launch_readiness')
                    ->label('وضعیت انتشار')
                    ->state(
                        fn (BakeryProduct $record): string => self::launchBlockers($record) === []
                                ? 'آماده انتشار'
                                : 'مسدود'
                    )
                    ->badge()
                    ->color(
                        fn (string $state): string => $state === 'آماده انتشار'
                                ? 'success'
                                : 'warning'
                    )
                    ->description(
                        fn (BakeryProduct $record): ?string => self::launchBlockerSummary($record)
                    ),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('دسته')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('variants_count')
                    ->label('Variant')
                    ->counts('variants'),
                Tables\Columns\TextColumn::make('variants_sum_stock_quantity')
                    ->label('موجودی کل')
                    ->sum('variants', 'stock_quantity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('availability_mode')
                    ->label('تأمین')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        BakeryProduct::AVAILABILITY_STOCKED => 'آماده',
                        BakeryProduct::AVAILABILITY_MADE_TO_ORDER => 'سفارشی',
                        default => 'تعیین نشده',
                    }),
                Tables\Columns\TextColumn::make('shipping_scope')
                    ->label('محدوده ارسال')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        BakeryProduct::SHIPPING_NATIONWIDE => 'سراسری',
                        BakeryProduct::SHIPPING_CONFIGURED_ZONES => 'محدوده‌های فعال',
                        BakeryProduct::SHIPPING_PICKUP_ONLY => 'حضوری',
                        default => 'تعیین نشده',
                    }),
                Tables\Columns\IconColumn::make('requires_cooling')
                    ->label('سرد')
                    ->boolean(),
                Tables\Columns\IconColumn::make('content_verified')
                    ->label('محتوا')
                    ->boolean(),
                Tables\Columns\IconColumn::make('media_verified')
                    ->label('رسانه')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('ویژه')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('دسته')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')->label('فعال'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('پیشنهادی'),
                Tables\Filters\SelectFilter::make('availability_mode')
                    ->label('مدل تأمین')
                    ->options([
                        BakeryProduct::AVAILABILITY_STOCKED => 'موجودی آماده',
                        BakeryProduct::AVAILABILITY_MADE_TO_ORDER => 'آماده‌سازی پس از سفارش',
                    ]),
                Tables\Filters\SelectFilter::make('shipping_scope')
                    ->label('محدوده ارسال')
                    ->options([
                        BakeryProduct::SHIPPING_NATIONWIDE => 'سراسری',
                        BakeryProduct::SHIPPING_CONFIGURED_ZONES => 'محدوده‌های تنظیم‌شده',
                        BakeryProduct::SHIPPING_PICKUP_ONLY => 'تحویل حضوری',
                    ]),
                Tables\Filters\TernaryFilter::make('requires_cooling')->label('نیازمند سرما'),
                Tables\Filters\TernaryFilter::make('content_verified')->label('محتوای تأییدشده'),
                Tables\Filters\TernaryFilter::make('media_verified')->label('رسانه تأییدشده'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    private static function launchReadinessSummary(
        ?BakeryProduct $record,
    ): string {
        if ($record === null) {
            return 'پس از ذخیره محصول، وضعیت دقیق انتشار اینجا نمایش داده می‌شود.';
        }

        $blockers = self::launchBlockers($record);

        if ($blockers === []) {
            return 'آماده انتشار — تمام Gateهای فعلی محصول عبور کرده‌اند.';
        }

        return 'مسدود — بر اساس آخرین وضعیت ذخیره‌شده: '
            .implode(' | ', $blockers);
    }

    private static function launchBlockerSummary(
        BakeryProduct $record,
    ): ?string {
        $blockers = self::launchBlockers($record);

        return $blockers === []
            ? null
            : implode('، ', $blockers);
    }

    /**
     * @return list<string>
     */
    private static function launchBlockers(
        BakeryProduct $record,
    ): array {
        $record->loadMissing([
            'category',
            'variants',
        ]);

        $activeVariants = $record->variants
            ->filter(
                fn ($variant): bool => (bool) $variant->is_active
            );

        $blockers = [];

        if (! (bool) $record->is_active) {
            $blockers[] = 'محصول غیرفعال است';
        }

        if (! (bool) ($record->category?->is_active ?? false)) {
            $blockers[] = 'دسته‌بندی غیرفعال است';
        }

        if ($activeVariants->isEmpty()) {
            $blockers[] = 'Variant قابل‌فروش فعال ندارد';
        }

        if (! (bool) $record->content_verified) {
            $blockers[] = 'اطلاعات محصول تأیید نشده است';
        }

        if (! (bool) $record->media_verified) {
            $blockers[] = 'رسانه محصول تأیید نشده است';
        }

        if (
            $activeVariants->contains(
                fn ($variant): bool => ! (bool) $variant->inventory_verified
            )
        ) {
            $blockers[] = 'موجودی Variant فعال تأیید نشده است';
        }

        return $blockers;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBakeryProducts::route('/'),
            'create' => Pages\CreateBakeryProduct::route('/create'),
            'edit' => Pages\EditBakeryProduct::route('/{record}/edit'),
        ];
    }
}
