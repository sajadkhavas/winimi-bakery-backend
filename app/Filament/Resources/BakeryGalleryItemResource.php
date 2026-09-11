<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryGalleryItemResource\Pages;
use App\Models\BakeryGalleryItem;
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
                ->required()
                ->helperText('ترتیب را از جدول نیز می‌توانید با جابه‌جایی ردیف‌ها تنظیم کنید.'),
            Forms\Components\Toggle::make('is_active')
                ->label('فعال')
                ->default(true),
            Forms\Components\Select::make('image_url')
                ->label('تصویر از کتابخانه رسانه')
                ->options(fn (?BakeryGalleryItem $record): array => AdminMediaLibrary::imageUrlOptions($record?->image_url))
                ->searchable()
                ->preload()
                ->required()
                ->helperText('تصویر را از کتابخانه رسانه تخصصی وینیمی انتخاب کنید. URL قدیمیِ فعلی در صورت وجود بدون حذف ناخواسته حفظ می‌شود.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('link_url')
                ->label('لینک مقصد')
                ->nullable()
                ->datalist([
                    '/',
                    '/products',
                    '/blog',
                    '/contact',
                    '/about',
                    '/gift',
                    '/corporate',
                ])
                ->helperText('برای صفحات داخل سایت مسیر با / وارد کنید. لینک کامل https نیز برای مقصد خارجی معتبر مجاز است.')
                ->rules([
                    'nullable',
                    'max:2048',
                    'regex:/^(\/(?!\/).*|https:\/\/\S+)$/i',
                ])
                ->columnSpanFull(),
            Forms\Components\Textarea::make('caption')
                ->label('توضیح')
                ->rows(3)
                ->maxLength(1000)
                ->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')->label('تصویر')->square(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('فعال')])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBakeryGalleryItems::route('/')];
    }
}
