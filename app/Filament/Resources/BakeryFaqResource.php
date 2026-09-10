<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryFaqResource\Pages;
use App\Models\BakeryFaq;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BakeryFaqResource extends Resource
{
    protected static ?string $model = BakeryFaq::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'سؤالات متداول';

    protected static ?string $modelLabel = 'سؤال متداول';

    protected static ?string $pluralModelLabel = 'سؤالات متداول';

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category')
                ->label('دسته')
                ->options(fn (): array => self::categoryOptions())
                ->searchable()
                ->preload()
                ->required()
                ->default('general'),
            Forms\Components\TextInput::make('sort_order')
                ->label('ترتیب')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required()
                ->helperText('در جدول نیز می‌توانید ترتیب سؤال‌ها را با حالت مرتب‌سازی جابه‌جا کنید.'),
            Forms\Components\Toggle::make('is_active')
                ->label('فعال')
                ->default(true),
            Forms\Components\TextInput::make('question')
                ->label('سؤال')
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\RichEditor::make('answer')
                ->label('پاسخ')
                ->required()
                ->toolbarButtons([
                    'blockquote',
                    'bold',
                    'bulletList',
                    'italic',
                    'link',
                    'orderedList',
                    'redo',
                    'strike',
                    'underline',
                    'undo',
                ])
                ->helperText('پاسخ را کوتاه و قابل اسکن نگه دارید؛ لینک و فهرست در صورت نیاز مجاز است.')
                ->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')->label('سؤال')->searchable()->limit(70),
                Tables\Columns\TextColumn::make('category')
                    ->label('دسته')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::categoryOptions()[$state] ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('دسته')
                    ->options(fn (): array => self::categoryOptions()),
                Tables\Filters\TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function categoryOptions(): array
    {
        $options = [
            'general' => 'عمومی',
            'home-decision' => 'راهنمای انتخاب در صفحه اصلی',
            'products' => 'محصولات و نگهداری',
            'orders' => 'سفارش و پرداخت',
            'delivery' => 'ارسال و تحویل',
        ];

        foreach (BakeryFaq::query()->distinct()->orderBy('category')->pluck('category') as $category) {
            $category = trim((string) $category);
            if ($category !== '' && ! array_key_exists($category, $options)) {
                $options[$category] = $category;
            }
        }

        return $options;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBakeryFaqs::route('/')];
    }
}
