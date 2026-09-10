<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryPostResource\Pages;
use App\Models\BakeryPost;
use App\Support\AdminMediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BakeryPostResource extends Resource
{
    protected static ?string $model = BakeryPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'وبلاگ بیکری';

    protected static ?string $modelLabel = 'مقاله';

    protected static ?string $pluralModelLabel = 'وبلاگ بیکری';

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 5;

    private const EDITOR_TOOLBAR = [
        'blockquote',
        'bold',
        'bulletList',
        'h2',
        'h3',
        'italic',
        'link',
        'orderedList',
        'redo',
        'strike',
        'underline',
        'undo',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('مقاله')
                ->description('محتوا با قالب‌های کنترل‌شده ذخیره می‌شود؛ عنوان‌های H2/H3 برای ساختار مقاله و فهرست مطالب Frontend استفاده می‌شوند.')
                ->schema([
                    Forms\Components\TextInput::make('title')->label('عنوان')->required()->maxLength(260)->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(180),
                    Forms\Components\TextInput::make('category')->label('دسته')->maxLength(120),
                    Forms\Components\TagsInput::make('tags')->label('برچسب‌ها')->columnSpanFull(),
                    Forms\Components\Textarea::make('excerpt')->label('خلاصه')->rows(3)->maxLength(500)->columnSpanFull(),
                    Forms\Components\RichEditor::make('content')
                        ->label('محتوا')
                        ->required()
                        ->toolbarButtons(self::EDITOR_TOOLBAR)
                        ->helperText('برای تیترهای داخلی از H2 و H3 استفاده کنید. کد، iframe و HTML دلخواه در محتوای عمومی پشتیبانی نمی‌شود.')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('cover_url')
                        ->label('تصویر شاخص از کتابخانه رسانه')
                        ->options(fn (?BakeryPost $record): array => AdminMediaLibrary::imageUrlOptions($record?->cover_url))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('از «کتابخانه رسانه» انتخاب کنید. URL فعلی قدیمی نیز برای جلوگیری از حذف ناخواسته حفظ می‌شود.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('author')->label('نویسنده')->maxLength(160),
                ])->columns(2),
            Forms\Components\Section::make('انتشار')
                ->description('با انتخاب «منتشرشده»، اگر زمان انتشار خالی باشد هنگام ذخیره به‌صورت خودکار زمان فعلی ثبت می‌شود؛ تاریخ آینده برای انتشار زمان‌بندی‌شده حفظ می‌شود.')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options(['draft' => 'پیش‌نویس', 'published' => 'منتشرشده'])
                        ->default('draft')
                        ->required(),
                    Forms\Components\DateTimePicker::make('published_at')->label('زمان انتشار'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('category')->label('دسته')->badge()->searchable(),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge(),
                Tables\Columns\TextColumn::make('published_at')->label('انتشار')->dateTime('Y/m/d H:i')->sortable(),
                Tables\Columns\TextColumn::make('view_count')->label('بازدید')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(['draft' => 'پیش‌نویس', 'published' => 'منتشرشده']),
                Tables\Filters\SelectFilter::make('category')
                    ->label('دسته')
                    ->options(fn (): array => BakeryPost::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category')->all()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([])
            ->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBakeryPosts::route('/')];
    }
}
