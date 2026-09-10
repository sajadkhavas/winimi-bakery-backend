<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryGalleryItemResource\Pages;
use App\Models\BakeryCategory;
use App\Models\BakeryContentPage;
use App\Models\BakeryGalleryItem;
use App\Models\BakeryPost;
use App\Models\BakeryProduct;
use App\Support\AdminMediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BakeryGalleryItemResource extends Resource
{
    protected static ?string $model = BakeryGalleryItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'گالری بیکری';

    protected static ?string $modelLabel = 'تصویر گالری';

    protected static ?string $pluralModelLabel = 'گالری بیکری';

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('عنوان')
                ->required()
                ->maxLength(220),
            Forms\Components\TextInput::make('sort_order')
                ->label('ترتیب')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\Toggle::make('is_active')
                ->label('فعال')
                ->default(true),
            Forms\Components\Select::make('image_url')
                ->label('تصویر از کتابخانه رسانه')
                ->options(fn (?BakeryGalleryItem $record): array => AdminMediaLibrary::imageUrlOptions($record?->image_url))
                ->searchable()
                ->preload()
                ->required()
                ->helperText('تصویر را از کتابخانه رسانه WINIMI انتخاب کنید. URL قدیمی رکوردهای موجود بدون حذف یا تغییر اجباری حفظ می‌شود.')
                ->columnSpanFull(),
            Forms\Components\Select::make('internal_link_picker')
                ->label('لینک داخلی پیشنهادی')
                ->options(fn (): array => self::internalLinkOptions())
                ->searchable()
                ->preload()
                ->dehydrated(false)
                ->live()
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    if (filled($state)) {
                        $set('link_url', $state);
                    }
                })
                ->helperText('برای جلوگیری از اشتباه Slug، یکی از مقصدهای منتشرشده را انتخاب کنید؛ مسیر مقصد به‌صورت خودکار در فیلد زیر قرار می‌گیرد.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('link_url')
                ->label('لینک مقصد')
                ->url()
                ->nullable()
                ->helperText('برای لینک خارجی می‌توانید URL کامل وارد کنید. برای لینک داخلی از انتخاب‌گر بالا استفاده کنید.')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('caption')
                ->label('توضیح')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')->label('تصویر')->square(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('فعال')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBakeryGalleryItems::route('/')];
    }

    /**
     * @return array<string, string>
     */
    public static function internalLinkOptions(): array
    {
        $options = [
            '/products' => 'فروشگاه — همه محصولات',
            '/blog' => 'محتوا — همه راهنماها',
        ];

        BakeryPost::query()
            ->published()
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->each(function (BakeryPost $post) use (&$options): void {
                $options['/blog/'.$post->slug] = 'مقاله — '.$post->title;
            });

        BakeryProduct::query()
            ->launchReady()
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->each(function (BakeryProduct $product) use (&$options): void {
                $options['/products/'.$product->slug] = 'محصول — '.$product->name;
            });

        BakeryCategory::query()
            ->active()
            ->ordered()
            ->get(['slug', 'name'])
            ->each(function (BakeryCategory $category) use (&$options): void {
                $options['/products/category/'.$category->slug] = 'دسته — '.$category->name;
            });

        BakeryContentPage::query()
            ->published()
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->each(function (BakeryContentPage $page) use (&$options): void {
                $options['/'.$page->slug] = 'صفحه — '.$page->title;
            });

        return $options;
    }
}
