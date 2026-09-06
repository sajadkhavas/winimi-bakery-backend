<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryCategoryLandingResource\Pages;
use App\Models\BakeryCategory;
use App\Models\BakeryCategoryLanding;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BakeryCategoryLandingResource extends Resource
{
    protected static ?string $model = BakeryCategoryLanding::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationLabel = 'لندینگ‌های سئو دسته‌ها';

    protected static ?string $modelLabel = 'لندینگ سئو دسته';

    protected static ?string $pluralModelLabel = 'لندینگ‌های سئو دسته‌ها';

    protected static ?string $navigationGroup = 'محتوا و سئو';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('مسیر و اتصال کاتالوگ')
                ->schema([
                    Forms\Components\TextInput::make('public_slug')
                        ->label('اسلاگ عمومی')
                        ->required()
                        ->alphaDash()
                        ->maxLength(140)
                        ->unique(ignoreRecord: true)
                        ->helperText('تغییر این مقدار روی URL و سئوی ثبت‌شده اثر مستقیم دارد.'),
                    Forms\Components\Select::make('catalog_category_slug')
                        ->label('دسته کاتالوگ')
                        ->options(fn (): array => BakeryCategory::query()->orderBy('name')->pluck('name', 'slug')->all())
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('catalog_search')
                        ->label('فیلتر جست‌وجوی کاتالوگ')
                        ->maxLength(100)
                        ->helperText('فقط برای landingهای مجازی مانند چیزکیک پر شود.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال')
                        ->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('ترتیب')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ])->columns(2),
            Forms\Components\Section::make('محتوا و SEO')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('نام نمایشی')->required()->maxLength(180),
                    Forms\Components\TextInput::make('eyebrow')->label('برچسب کوتاه')->maxLength(120),
                    Forms\Components\Textarea::make('card_description')->label('توضیح کارت')->rows(3)->columnSpanFull(),
                    Forms\Components\TextInput::make('meta_title')->label('SEO Title')->required()->maxLength(70),
                    Forms\Components\Textarea::make('meta_description')->label('Meta Description')->required()->maxLength(180)->rows(3)->columnSpanFull(),
                    Forms\Components\TextInput::make('heading')->label('H1')->required()->maxLength(220)->columnSpanFull(),
                    Forms\Components\Textarea::make('intro')->label('مقدمه')->required()->rows(5)->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('بخش‌های محتوایی و لینک‌سازی داخلی')
                ->schema([
                    Forms\Components\Repeater::make('sections')
                        ->label('بخش‌ها')
                        ->schema([
                            Forms\Components\TextInput::make('title')->label('عنوان')->required()->maxLength(220),
                            Forms\Components\Textarea::make('body')->label('متن')->required()->rows(4),
                        ])
                        ->minItems(1)
                        ->reorderable()
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('faq')
                        ->label('FAQ مخصوص همین landing')
                        ->schema([
                            Forms\Components\TextInput::make('question')->label('پرسش')->required()->maxLength(255),
                            Forms\Components\Textarea::make('answer')->label('پاسخ')->required()->rows(4),
                        ])
                        ->reorderable()
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('guides')
                        ->label('راهنماهای مرتبط (Internal Linking)')
                        ->schema([
                            Forms\Components\TextInput::make('href')
                                ->label('مسیر داخلی')
                                ->required()
                                ->startsWith('/')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('title')
                                ->label('عنوان لینک')
                                ->required()
                                ->maxLength(220),
                            Forms\Components\Textarea::make('description')
                                ->label('توضیح')
                                ->required()
                                ->rows(3),
                        ])
                        ->reorderable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('public_slug')->label('URL')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('catalog_category_slug')->label('دسته کاتالوگ')->badge(),
                Tables\Columns\TextColumn::make('catalog_search')->label('فیلتر مجازی')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBakeryCategoryLandings::route('/'),
        ];
    }
}
