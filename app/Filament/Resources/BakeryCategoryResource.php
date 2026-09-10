<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryCategoryResource\Pages;
use App\Models\BakeryCategory;
use App\Support\AdminMediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BakeryCategoryResource extends Resource
{
    protected static ?string $model = BakeryCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'دسته‌های بیکری';

    protected static ?string $modelLabel = 'دسته بیکری';

    protected static ?string $pluralModelLabel = 'دسته‌های بیکری';

    protected static ?string $navigationGroup = 'فروشگاه';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات دسته')
                ->description('تصویر دسته از کتابخانه رسانه مرکزی WINIMI انتخاب می‌شود؛ همان تصویر در API کاتالوگ و کارت دسته صفحه اصلی مرجع است.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('نام دسته')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(140)
                        ->helperText('در صورت خالی‌بودن از نام ساخته می‌شود.'),
                    Forms\Components\Textarea::make('description')
                        ->label('توضیح دسته')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('image_path')
                        ->label('تصویر دسته از کتابخانه رسانه')
                        ->options(fn (?BakeryCategory $record): array => AdminMediaLibrary::imagePathOptions($record?->image_path))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('فقط نسخه‌های WebP آماده و حداکثر ۱MB قابل انتخاب‌اند. تصویر قدیمی فعلی برای جلوگیری از حذف ناخواسته حفظ می‌شود.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('image_alt')
                        ->label('متن جایگزین تصویر (Alt)')
                        ->maxLength(255)
                        ->helperText('تصویر را کوتاه، دقیق و طبیعی توصیف کنید؛ از تکرار مصنوعی کلمات کلیدی پرهیز شود. اگر خالی بماند نام دسته به‌عنوان fallback استفاده می‌شود.')
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال در فروشگاه')
                        ->helperText('فعال بودن دسته شرط لازم انتشار محصولات آن است، اما به‌تنهایی هیچ محصولی را منتشر نمی‌کند.')
                        ->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('ترتیب نمایش')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ])
                ->columns(2),
            Forms\Components\Section::make('سئو')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->label('عنوان سئو')
                        ->maxLength(70),
                    Forms\Components\Textarea::make('meta_description')
                        ->label('توضیح سئو')
                        ->maxLength(180)
                        ->rows(3),
                ])
                ->columns(2)
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('تصویر')
                    ->disk('public')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('image_alt')
                    ->label('Alt تصویر')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('محصولات')
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBakeryCategories::route('/'),
            'create' => Pages\CreateBakeryCategory::route('/create'),
            'edit' => Pages\EditBakeryCategory::route('/{record}/edit'),
        ];
    }
}
