<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryMediaAssetResource\Pages;
use App\Models\BakeryMediaAsset;
use App\Models\BakeryProduct;
use App\Support\AdminImageUpload;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class BakeryMediaAssetResource extends Resource
{
    protected static ?string $model = BakeryMediaAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'کتابخانه رسانه';

    protected static ?string $modelLabel = 'رسانه';

    protected static ?string $pluralModelLabel = 'کتابخانه رسانه';

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 1;

    public static function maxUploadSizeKilobytes(): int
    {
        return AdminImageUpload::maxKilobytes();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                SpatieMediaLibraryFileUpload::make('source_image')
                    ->label('فایل اصلی')
                    ->collection('source')
                    ->image()
                    ->imageEditor()
                    ->acceptedFileTypes(AdminImageUpload::acceptedMimeTypes())
                    ->maxSize(self::maxUploadSizeKilobytes())
                    ->rules(AdminImageUpload::dimensionRules())
                    ->required(fn (?BakeryMediaAsset $record): bool => $record === null)
                    ->disabled(fn (?BakeryMediaAsset $record): bool => $record !== null)
                    ->helperText(fn (?BakeryMediaAsset $record): string => $record === null
                        ? 'JPEG، PNG یا WebP تا '.AdminImageUpload::maxMegabytesLabel().' و حداکثر '.AdminImageUpload::MAX_WIDTH.'×'.AdminImageUpload::MAX_HEIGHT.' پیکسل. فایل اصلی حفظ می‌شود و Preview/Thumbnail به WebP تبدیل می‌شوند.'
                        : 'فایل اصلی این رکورد قفل است چون ممکن است دسته‌ها، صفحات و محتوای سایت به URL/مسیر نسخه بهینه آن ارجاع داشته باشند. برای جایگزینی تصویر، رسانه جدید بسازید و مقصد را به آن تغییر دهید.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title')
                    ->label('عنوان داخلی')
                    ->required()
                    ->maxLength(220),
                Forms\Components\TextInput::make('alt_text')
                    ->label('متن جایگزین (Alt)')
                    ->maxLength(500)
                    ->helperText('برای دسترس‌پذیری و SEO، تصویر را کوتاه و دقیق توصیف کنید.'),
                Forms\Components\Select::make('product_id')
                    ->label('محصول مرتبط')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Select::make('usage')
                    ->label('نوع استفاده')
                    ->options([
                        BakeryMediaAsset::USAGE_UNASSIGNED => 'تخصیص‌نیافته',
                        BakeryMediaAsset::USAGE_PRODUCT_MAIN => 'تصویر اصلی محصول',
                        BakeryMediaAsset::USAGE_PRODUCT_GALLERY => 'گالری محصول',
                        BakeryMediaAsset::USAGE_HERO => 'Hero / بنر',
                        BakeryMediaAsset::USAGE_BRAND => 'برند / Lifestyle',
                        BakeryMediaAsset::USAGE_CATEGORY => 'دسته‌بندی',
                    ])
                    ->default(BakeryMediaAsset::USAGE_UNASSIGNED)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('وضعیت')
                    ->options([
                        BakeryMediaAsset::STATUS_PENDING => 'نیازمند بررسی',
                        BakeryMediaAsset::STATUS_READY => 'آماده تخصیص',
                        BakeryMediaAsset::STATUS_ASSIGNED => 'تخصیص داده شده',
                        BakeryMediaAsset::STATUS_REJECTED => 'رد شده',
                    ])
                    ->helperText('انتخاب «آماده تخصیص» فقط وقتی ذخیره می‌شود که Conversionها کامل و Preview بهینه حداکثر ۱MB باشد.')
                    ->default(BakeryMediaAsset::STATUS_PENDING)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('یادداشت داخلی')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview')
                    ->label('تصویر')
                    ->getStateUsing(fn (BakeryMediaAsset $record): ?string => $record->previewUrl())
                    ->square(),
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('conversion_state')
                    ->label('نسخه مصرفی')
                    ->state(fn (BakeryMediaAsset $record): string => $record->conversionState())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ready' => 'WebP آماده ≤ 1MB',
                        'oversized' => 'بیشتر از 1MB',
                        'missing' => 'فایل اصلی ندارد',
                        'broken' => 'فایل/Storage ناسالم',
                        default => 'در انتظار پردازش',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'success',
                        'oversized', 'missing', 'broken' => 'danger',
                        default => 'warning',
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('original_size')
                    ->label('حجم Original')
                    ->state(fn (BakeryMediaAsset $record): string => $record->originalSizeLabel()),
                Tables\Columns\TextColumn::make('optimized_size')
                    ->label('حجم Optimized')
                    ->state(fn (BakeryMediaAsset $record): string => $record->optimizedSizeLabel()),
                Tables\Columns\TextColumn::make('dimensions')
                    ->label('ابعاد Original')
                    ->state(fn (BakeryMediaAsset $record): string => $record->dimensionsLabel())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('format')
                    ->label('فرمت Original')
                    ->state(fn (BakeryMediaAsset $record): string => $record->formatLabel())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('original_url')
                    ->label('URL اصلی')
                    ->state(fn (BakeryMediaAsset $record): ?string => $record->originalUrl())
                    ->copyable()
                    ->copyMessage('URL اصلی کپی شد')
                    ->limit(18)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('optimized_url')
                    ->label('URL بهینه')
                    ->state(fn (BakeryMediaAsset $record): ?string => $record->optimizedUrl())
                    ->copyable()
                    ->copyMessage('URL بهینه کپی شد')
                    ->limit(18)
                    ->placeholder('در انتظار پردازش'),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('محصول')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('usage')
                    ->label('کاربرد')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BakeryMediaAsset::USAGE_PRODUCT_MAIN => 'تصویر اصلی محصول',
                        BakeryMediaAsset::USAGE_PRODUCT_GALLERY => 'گالری محصول',
                        BakeryMediaAsset::USAGE_HERO => 'Hero / بنر',
                        BakeryMediaAsset::USAGE_BRAND => 'برند / Lifestyle',
                        BakeryMediaAsset::USAGE_CATEGORY => 'دسته‌بندی',
                        default => 'تخصیص‌نیافته',
                    })
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BakeryMediaAsset::STATUS_READY => 'آماده',
                        BakeryMediaAsset::STATUS_ASSIGNED => 'تخصیص‌شده',
                        BakeryMediaAsset::STATUS_REJECTED => 'رد شده',
                        default => 'نیازمند بررسی',
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        BakeryMediaAsset::STATUS_PENDING => 'نیازمند بررسی',
                        BakeryMediaAsset::STATUS_READY => 'آماده',
                        BakeryMediaAsset::STATUS_ASSIGNED => 'تخصیص‌شده',
                        BakeryMediaAsset::STATUS_REJECTED => 'رد شده',
                    ]),
                Tables\Filters\SelectFilter::make('usage')
                    ->label('کاربرد')
                    ->options([
                        BakeryMediaAsset::USAGE_UNASSIGNED => 'تخصیص‌نیافته',
                        BakeryMediaAsset::USAGE_PRODUCT_MAIN => 'تصویر اصلی محصول',
                        BakeryMediaAsset::USAGE_PRODUCT_GALLERY => 'گالری محصول',
                        BakeryMediaAsset::USAGE_HERO => 'Hero / بنر',
                        BakeryMediaAsset::USAGE_BRAND => 'برند / Lifestyle',
                        BakeryMediaAsset::USAGE_CATEGORY => 'دسته‌بندی',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('regeneratePreviews')
                    ->label('بازسازی نسخه بهینه')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (BakeryMediaAsset $record): bool => $record->sourceMedia() !== null && ! $record->publicPreviewWithinBudget())
                    ->requiresConfirmation()
                    ->modalDescription('فقط نسخه‌های مشتق‌شده thumb و preview دوباره ساخته می‌شوند؛ فایل Original حذف یا جایگزین نمی‌شود.')
                    ->action(function (BakeryMediaAsset $record): void {
                        $media = $record->sourceMedia();
                        if ($media === null) {
                            Notification::make()->danger()->title('فایل اصلی پیدا نشد.')->send();

                            return;
                        }

                        try {
                            Artisan::call('media-library:regenerate', [
                                '--ids' => [(string) $media->getKey()],
                                '--only' => ['thumb', 'preview'],
                                '--force' => true,
                            ]);

                            $record->unsetRelation('media');
                            $record->load('media');

                            if ($record->publicPreviewWithinBudget()) {
                                Notification::make()
                                    ->success()
                                    ->title('نسخه WebP آماده و داخل بودجه ۱MB است.')
                                    ->body('حجم Optimized: '.$record->optimizedSizeLabel())
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('پردازش انجام شد اما نسخه مصرفی هنوز آماده استفاده نیست.')
                                    ->body('وضعیت: '.$record->optimizedSizeLabel().' — تا رفع مشکل رسانه را Ready نکنید.')
                                    ->send();
                            }
                        } catch (\Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('بازسازی نسخه بهینه ناموفق بود.')->send();
                        }
                    }),
                Tables\Actions\Action::make('assignToProduct')
                    ->label('اتصال به محصول')
                    ->icon('heroicon-o-link')
                    ->visible(fn (BakeryMediaAsset $record): bool => $record->status === BakeryMediaAsset::STATUS_READY && $record->publicPreviewWithinBudget())
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('محصول مقصد')
                            ->options(fn (): array => BakeryProduct::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('usage')
                            ->label('محل استفاده')
                            ->options([
                                BakeryMediaAsset::USAGE_PRODUCT_MAIN => 'تصویر اصلی محصول',
                                BakeryMediaAsset::USAGE_PRODUCT_GALLERY => 'گالری محصول',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('alt_text')
                            ->label('متن جایگزین تصویر')
                            ->maxLength(500)
                            ->helperText('در صورت خالی بودن، Alt ثبت‌شده در کتابخانه یا عنوان رسانه استفاده می‌شود.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('اتصال رسانه به محصول')
                    ->modalDescription('فایل اصلی در کتابخانه حفظ می‌شود. اگر مقصد «تصویر اصلی» باشد و محصول از قبل تصویر اصلی داشته باشد، عملیات رد می‌شود و هیچ جایگزینی خودکاری انجام نخواهد شد.')
                    ->action(function (BakeryMediaAsset $record, array $data): void {
                        $product = BakeryProduct::query()->findOrFail((int) $data['product_id']);

                        try {
                            $record->assignToProduct(
                                $product,
                                (string) $data['usage'],
                                isset($data['alt_text']) ? (string) $data['alt_text'] : null,
                            );
                        } catch (\DomainException|\InvalidArgumentException $exception) {
                            Notification::make()->danger()->title('اتصال انجام نشد')->body($exception->getMessage())->send();

                            return;
                        } catch (\Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('خطای غیرمنتظره در اتصال رسانه')->send();

                            return;
                        }

                        Notification::make()->success()->title('رسانه به محصول متصل شد.')->send();
                    }),
                Tables\Actions\EditAction::make()->label('ویرایش'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('assignGallery')
                    ->label('اتصال گروهی به گالری محصول')
                    ->icon('heroicon-o-photo')
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('محصول مقصد')
                            ->options(fn (): array => BakeryProduct::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('اتصال گروهی به گالری')
                    ->modalDescription('فقط رسانه‌های Ready با نسخه WebP حداکثر ۱MB پردازش می‌شوند. Original هر تصویر در کتابخانه باقی می‌ماند.')
                    ->action(function ($records, array $data): void {
                        $product = BakeryProduct::query()->findOrFail((int) $data['product_id']);
                        $assigned = 0;
                        $failed = 0;

                        foreach ($records as $record) {
                            if (! $record instanceof BakeryMediaAsset || ! $record->publicPreviewWithinBudget()) {
                                $failed++;
                                continue;
                            }

                            try {
                                $record->assignToProduct(
                                    $product,
                                    BakeryMediaAsset::USAGE_PRODUCT_GALLERY,
                                    $record->alt_text ?: $record->title,
                                );
                                $assigned++;
                            } catch (\Throwable $exception) {
                                report($exception);
                                $failed++;
                            }
                        }

                        $notification = Notification::make()
                            ->title($failed === 0 ? 'اتصال گروهی کامل شد.' : 'اتصال گروهی با هشدار تمام شد.')
                            ->body("موفق: {$assigned} | ناموفق: {$failed}");

                        if ($failed === 0) {
                            $notification->success();
                        } else {
                            $notification->warning();
                        }

                        $notification->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBakeryMediaAssets::route('/'),
        ];
    }
}
