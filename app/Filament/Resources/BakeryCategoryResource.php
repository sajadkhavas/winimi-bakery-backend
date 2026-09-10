<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryCategoryResource\Pages;
use App\Models\BakeryCategory;
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

    protected static ?string $navigationGroup = 'فروشگاه وینیمی';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات دسته')
                ->description('نام، توضیح و تصویر اینجا منبع اصلی نمایش دسته در کاتالوگ و بخش‌های انتخاب‌شده سایت هستند.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('نام دسته')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(140)
                        ->helperText('در صورت خالی‌بودن از نام ساخته می‌شود. تغییر Slug بعد از انتشار، URL و سئو را تغییر می‌دهد.'),
                    Forms\Components\Textarea::make('description')
                        ->label('توضیح دسته')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image_path')
                        ->label('تصویر اصلی دسته')
                        ->image()
                        ->imageEditor()
                        ->directory('bakery/categories')
                        ->helperText('همین تصویر در کارت‌های دسته استفاده می‌شود و بر تصویر fallback فرانت اولویت دارد.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('image_alt')
                        ->label('متن جایگزین تصویر (Alt)')
                        ->maxLength(255)
                        ->helperText('تصویر را کوتاه، دقیق و طبیعی توصیف کنید؛ اگر خالی بماند نام دسته استفاده می‌شود.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('نمایش و ترتیب در سایت')
                ->description('فعال‌بودن، حضور در صفحه اصلی و حضور در فوتر مستقل هستند. منوی هدر و زیرمنوها از بخش «منوهای هدر و فوتر» مدیریت می‌شود.')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال در فروشگاه')
                        ->helperText('اگر خاموش شود، دسته و محصولات وابسته از کاتالوگ عمومی خارج می‌شوند.')
                        ->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('ترتیب اصلی کاتالوگ')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\Toggle::make('show_on_home')
                        ->label('نمایش در دسته‌های صفحه اصلی')
                        ->default(true),
                    Forms\Components\TextInput::make('home_sort_order')
                        ->label('ترتیب در صفحه اصلی')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('همان ترتیب اصلی')
                        ->helperText('اگر خالی باشد «ترتیب اصلی کاتالوگ» استفاده می‌شود.'),
                    Forms\Components\Toggle::make('show_in_footer')
                        ->label('نمایش در فوتر')
                        ->default(true),
                    Forms\Components\TextInput::make('footer_sort_order')
                        ->label('ترتیب در فوتر')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('همان ترتیب اصلی')
                        ->helperText('اگر خالی باشد «ترتیب اصلی کاتالوگ» استفاده می‌شود.'),
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
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('محصولات')
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('فروشگاه')
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_on_home')
                    ->label('خانه')
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_in_footer')
                    ->label('فوتر')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب اصلی')
                    ->sortable(),
                Tables\Columns\TextColumn::make('image_alt')
                    ->label('Alt تصویر')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('فعال در فروشگاه'),
                Tables\Filters\TernaryFilter::make('show_on_home')->label('نمایش صفحه اصلی'),
                Tables\Filters\TernaryFilter::make('show_in_footer')->label('نمایش فوتر'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBakeryCategories::route('/'),
            'create' => Pages\CreateBakeryCategory::route('/create'),
            'edit' => Pages\EditBakeryCategory::route('/{record}/edit'),
        ];
    }
}
