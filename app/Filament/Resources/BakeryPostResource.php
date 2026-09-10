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
use FilamentTiptapEditor\Enums\TiptapOutput;
use FilamentTiptapEditor\TiptapEditor;

class BakeryPostResource extends Resource
{
    protected static ?string $model = BakeryPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'وبلاگ بیکری';

    protected static ?string $modelLabel = 'مقاله';

    protected static ?string $pluralModelLabel = 'وبلاگ بیکری';

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 5;

    private const EDITOR_TOOLS = [
        'heading',
        'hr',
        'bullet-list',
        'ordered-list',
        'checked-list',
        'blockquote',
        'bold',
        'italic',
        'strike',
        'underline',
        'lead',
        'small',
        'link',
        'table',
        'details',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('مقاله')
                ->description('ویرایشگر حرفه‌ای با خروجی HTML سازگار با Frontend فعال است. ابزارهای اجرای کد، HTML خام، iframe، Embed و آپلود رسانه داخل متن عمداً غیرفعال‌اند؛ تصویر شاخص از کتابخانه رسانه انتخاب می‌شود.')
                ->schema([
                    Forms\Components\TextInput::make('title')->label('عنوان')->required()->maxLength(260)->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(180),
                    Forms\Components\TextInput::make('category')->label('دسته')->maxLength(120),
                    Forms\Components\TagsInput::make('tags')->label('برچسب‌ها')->columnSpanFull(),
                    Forms\Components\Textarea::make('excerpt')->label('خلاصه')->rows(3)->maxLength(500)->columnSpanFull(),
                    TiptapEditor::make('content')
                        ->label('محتوا')
                        ->required()
                        ->tools(self::EDITOR_TOOLS)
                        ->output(TiptapOutput::Html)
                        ->maxContentWidth('full')
                        ->extraInputAttributes(['style' => 'min-height: 20rem;'])
                        ->helperText('برای ساختار خوانا از Heading، فهرست، نقل‌قول، جدول، جزئیات و لینک استفاده کنید. محتوای ذخیره‌شده HTML باقی می‌ماند تا قرارداد فعلی Frontend تغییر نکند.')
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
