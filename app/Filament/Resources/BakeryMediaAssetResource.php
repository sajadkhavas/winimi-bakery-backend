<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryMediaAssetResource\Pages;
use App\Models\BakeryMediaAsset;
use App\Models\BakeryProduct;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BakeryMediaAssetResource extends Resource
{
    protected static ?string $model = BakeryMediaAsset::class;

    protected static ?string $navigationIcon =
        'heroicon-o-photo';

    protected static ?string $navigationLabel =
        'کتابخانه رسانه';

    protected static ?string $modelLabel =
        'رسانه';

    protected static ?string $pluralModelLabel =
        'کتابخانه رسانه';

    protected static ?string $navigationGroup =
        'فروشگاه وینیمی';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                SpatieMediaLibraryFileUpload::make(
                    'source_image'
                )
                    ->label('فایل اصلی')
                    ->collection('source')
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
                        'فایل اصلی حفظ می‌شود؛ نسخه‌های WebP پیش‌نمایش به‌صورت خودکار ساخته می‌شوند.'
                    )
                    ->columnSpanFull(),

                Forms\Components\TextInput::make(
                    'title'
                )
                    ->label('عنوان داخلی')
                    ->required()
                    ->maxLength(220),

                Forms\Components\TextInput::make(
                    'alt_text'
                )
                    ->label('Alt پیشنهادی')
                    ->maxLength(500),

                Forms\Components\Select::make(
                    'product_id'
                )
                    ->label('محصول مرتبط')
                    ->relationship(
                        'product',
                        'name',
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Select::make(
                    'usage'
                )
                    ->label('نوع استفاده')
                    ->options([
                        BakeryMediaAsset::USAGE_UNASSIGNED => 'تخصیص‌نیافته',

                        BakeryMediaAsset::USAGE_PRODUCT_MAIN => 'تصویر اصلی محصول',

                        BakeryMediaAsset::USAGE_PRODUCT_GALLERY => 'گالری محصول',

                        BakeryMediaAsset::USAGE_HERO => 'Hero / بنر',

                        BakeryMediaAsset::USAGE_BRAND => 'برند / Lifestyle',

                        BakeryMediaAsset::USAGE_CATEGORY => 'دسته‌بندی',
                    ])
                    ->default(
                        BakeryMediaAsset::USAGE_UNASSIGNED
                    )
                    ->required(),

                Forms\Components\Select::make(
                    'status'
                )
                    ->label('وضعیت')
                    ->options([
                        BakeryMediaAsset::STATUS_PENDING => 'نیازمند بررسی',

                        BakeryMediaAsset::STATUS_READY => 'آماده تخصیص',

                        BakeryMediaAsset::STATUS_ASSIGNED => 'تخصیص داده شده',

                        BakeryMediaAsset::STATUS_REJECTED => 'رد شده',
                    ])
                    ->default(
                        BakeryMediaAsset::STATUS_PENDING
                    )
                    ->required(),

                Forms\Components\Textarea::make(
                    'notes'
                )
                    ->label('یادداشت داخلی')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(
        Table $table,
    ): Table {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make(
                    'preview'
                )
                    ->label('تصویر')
                    ->getStateUsing(
                        fn (
                            BakeryMediaAsset $record
                        ): ?string => $record->previewUrl(),
                    )
                    ->square(),

                Tables\Columns\TextColumn::make(
                    'title'
                )
                    ->label('عنوان')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make(
                    'product.name'
                )
                    ->label('محصول')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make(
                    'usage'
                )
                    ->label('کاربرد')
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            BakeryMediaAsset::USAGE_PRODUCT_MAIN => 'تصویر اصلی محصول',

                            BakeryMediaAsset::USAGE_PRODUCT_GALLERY => 'گالری محصول',

                            BakeryMediaAsset::USAGE_HERO => 'Hero / بنر',

                            BakeryMediaAsset::USAGE_BRAND => 'برند / Lifestyle',

                            BakeryMediaAsset::USAGE_CATEGORY => 'دسته‌بندی',

                            default => 'تخصیص‌نیافته',
                        },
                    )
                    ->badge(),

                Tables\Columns\TextColumn::make(
                    'status'
                )
                    ->label('وضعیت')
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            BakeryMediaAsset::STATUS_READY => 'آماده',

                            BakeryMediaAsset::STATUS_ASSIGNED => 'تخصیص‌شده',

                            BakeryMediaAsset::STATUS_REJECTED => 'رد شده',

                            default => 'نیازمند بررسی',
                        },
                    )
                    ->badge(),

                Tables\Columns\TextColumn::make(
                    'updated_at'
                )
                    ->label('آخرین تغییر')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make(
                    'status'
                )
                    ->label('وضعیت')
                    ->options([
                        BakeryMediaAsset::STATUS_PENDING => 'نیازمند بررسی',

                        BakeryMediaAsset::STATUS_READY => 'آماده',

                        BakeryMediaAsset::STATUS_ASSIGNED => 'تخصیص‌شده',

                        BakeryMediaAsset::STATUS_REJECTED => 'رد شده',
                    ]),

                Tables\Filters\SelectFilter::make(
                    'usage'
                )
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
                Tables\Actions\Action::make(
                    'assignToProduct'
                )
                    ->label('اتصال به محصول')
                    ->icon(
                        'heroicon-o-link'
                    )
                    ->visible(
                        fn (
                            BakeryMediaAsset $record
                        ): bool => $record->status
                                === BakeryMediaAsset::STATUS_READY
                            && $record->sourceMedia() !== null
                    )
                    ->form([
                        Forms\Components\Select::make(
                            'product_id'
                        )
                            ->label('محصول مقصد')
                            ->options(
                                fn (): array => BakeryProduct::query()
                                    ->orderBy('name')
                                    ->pluck(
                                        'name',
                                        'id',
                                    )
                                    ->all()
                            )
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make(
                            'usage'
                        )
                            ->label('محل استفاده')
                            ->options([
                                BakeryMediaAsset::USAGE_PRODUCT_MAIN => 'تصویر اصلی محصول',

                                BakeryMediaAsset::USAGE_PRODUCT_GALLERY => 'گالری محصول',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make(
                            'alt_text'
                        )
                            ->label(
                                'متن جایگزین تصویر'
                            )
                            ->maxLength(500)
                            ->helperText(
                                'در صورت خالی بودن، Alt ثبت‌شده در کتابخانه یا عنوان رسانه استفاده می‌شود.'
                            ),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading(
                        'اتصال رسانه به محصول'
                    )
                    ->modalDescription(
                        'فایل اصلی در کتابخانه حفظ می‌شود. اگر مقصد «تصویر اصلی» باشد و محصول از قبل تصویر اصلی داشته باشد، عملیات رد می‌شود و هیچ جایگزینی خودکاری انجام نخواهد شد.'
                    )
                    ->action(
                        function (
                            BakeryMediaAsset $record,
                            array $data,
                        ): void {
                            $product =
                                BakeryProduct::query()
                                    ->findOrFail(
                                        (int) $data[
                                            'product_id'
                                        ]
                                    );

                            try {
                                $record->assignToProduct(
                                    $product,
                                    (string) $data[
                                        'usage'
                                    ],
                                    isset(
                                        $data[
                                            'alt_text'
                                        ]
                                    )
                                        ? (string) $data[
                                            'alt_text'
                                        ]
                                        : null,
                                );
                            } catch (
                                \DomainException
                                |\InvalidArgumentException
                                $exception
                            ) {
                                Notification::make()
                                    ->danger()
                                    ->title(
                                        'اتصال انجام نشد'
                                    )
                                    ->body(
                                        $exception
                                            ->getMessage()
                                    )
                                    ->send();

                                return;
                            } catch (
                                \Throwable $exception
                            ) {
                                report($exception);

                                Notification::make()
                                    ->danger()
                                    ->title(
                                        'خطای غیرمنتظره در اتصال رسانه'
                                    )
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title(
                                    'رسانه به محصول متصل شد.'
                                )
                                ->send();
                        },
                    ),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make(
                    'assignGallery'
                )
                    ->label(
                        'اتصال گروهی به گالری محصول'
                    )
                    ->icon(
                        'heroicon-o-photo'
                    )
                    ->form([
                        Forms\Components\Select::make(
                            'product_id'
                        )
                            ->label('محصول مقصد')
                            ->options(
                                fn (): array => BakeryProduct::query()
                                    ->orderBy('name')
                                    ->pluck(
                                        'name',
                                        'id',
                                    )
                                    ->all()
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading(
                        'اتصال گروهی به گالری'
                    )
                    ->modalDescription(
                        'فقط رسانه‌های آماده تخصیص پردازش می‌شوند. Source اصلی هر تصویر در کتابخانه باقی می‌ماند.'
                    )
                    ->action(
                        function (
                            $records,
                            array $data,
                        ): void {
                            $product =
                                BakeryProduct::query()
                                    ->findOrFail(
                                        (int) $data[
                                            'product_id'
                                        ]
                                    );

                            $assigned = 0;
                            $failed = 0;

                            foreach (
                                $records as $record
                            ) {
                                if (
                                    ! $record
                                        instanceof BakeryMediaAsset
                                ) {
                                    $failed++;

                                    continue;
                                }

                                try {
                                    $record
                                        ->assignToProduct(
                                            $product,
                                            BakeryMediaAsset::USAGE_PRODUCT_GALLERY,
                                            $record
                                                ->alt_text
                                            ?: $record
                                                ->title,
                                        );

                                    $assigned++;
                                } catch (
                                    \Throwable
                                    $exception
                                ) {
                                    report(
                                        $exception
                                    );

                                    $failed++;
                                }
                            }

                            $notification =
                                Notification::make()
                                    ->title(
                                        $failed === 0
                                            ? 'اتصال گروهی کامل شد.'
                                            : 'اتصال گروهی با هشدار تمام شد.'
                                    )
                                    ->body(
                                        "موفق: {$assigned} | ناموفق: {$failed}"
                                    );

                            if ($failed === 0) {
                                $notification
                                    ->success();
                            } else {
                                $notification
                                    ->warning();
                            }

                            $notification->send();
                        },
                    ),
            ])
            ->defaultSort(
                'created_at',
                'desc',
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBakeryMediaAssets::route(
                '/'
            ),
        ];
    }
}
