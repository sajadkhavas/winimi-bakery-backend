<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BakeryContentPageResource\Pages;
use App\Models\BakeryContentPage;
use App\Support\AdminMediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use FilamentTiptapEditor\Enums\TiptapOutput;
use FilamentTiptapEditor\TiptapEditor;

class BakeryContentPageResource extends Resource
{
    protected static ?string $model = BakeryContentPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'صفحات فروشگاه';

    protected static ?string $modelLabel = 'صفحه';

    protected static ?string $pluralModelLabel = 'صفحات فروشگاه';

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 1;

    private const PROTECTED_SLUGS = [
        'about',
        'shipping',
        'privacy',
        'terms',
        'quality',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('محتوا')
                ->description('صفحات عمومی با Editor مدیریت‌شده WINIMI و Media/Link Picker مرکزی ویرایش می‌شوند. HTML خام، کد اجرایی و Embed عمومی عمداً در دسترس نیستند.')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('نوع')
                        ->options([
                            'page' => 'صفحه عمومی',
                            'legal' => 'قوانین و حریم خصوصی',
                            'shipping' => 'ارسال و تحویل',
                            'homepage' => 'محتوای صفحه اصلی',
                        ])
                        ->default('page')
                        ->required(),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(160)
                        ->disabled(fn (?BakeryContentPage $record): bool => $record !== null && self::isProtectedPage($record))
                        ->dehydrated(fn (?BakeryContentPage $record): bool => $record === null || ! self::isProtectedPage($record))
                        ->helperText('Slug صفحات هسته درباره ما، ارسال، حریم خصوصی، قوانین و کیفیت محافظت می‌شود تا مسیرهای عمومی سایت ناخواسته خراب نشوند.'),
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان')
                        ->required()
                        ->maxLength(220)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('excerpt')
                        ->label('خلاصه')
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('cover_url')
                        ->label('تصویر شاخص از کتابخانه رسانه')
                        ->options(fn (?BakeryContentPage $record): array => AdminMediaLibrary::imageUrlOptions($record?->cover_url))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('اختیاری؛ از کتابخانه رسانه مرکزی انتخاب کنید. مقدار قدیمی فعلی فقط برای جلوگیری از حذف ناخواسته حفظ می‌شود.')
                        ->columnSpanFull(),
                    TiptapEditor::make('content')
                        ->label('متن')
                        ->required()
                        ->profile('default')
                        ->output(TiptapOutput::Html)
                        ->maxContentWidth('full')
                        ->extraInputAttributes(['style' => 'min-height: 20rem;'])
                        ->helperText('Heading، فهرست، نقل‌قول، رنگ/Highlight، Alignment، جدول، لینک داخلی و تصویر از Media Library در دسترس است.')
                        ->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('انتشار و سئو')
                ->description('انتشار بدون زمان، هنگام ذخیره زمان فعلی می‌گیرد؛ تاریخ آینده برای انتشار زمان‌بندی‌شده حفظ می‌شود.')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options(['draft' => 'پیش‌نویس', 'published' => 'منتشرشده'])
                        ->default('draft')
                        ->required(),
                    Forms\Components\DateTimePicker::make('published_at')->label('زمان انتشار'),
                    Forms\Components\TextInput::make('meta_title')->label('عنوان سئو')->maxLength(220),
                    Forms\Components\Textarea::make('meta_description')->label('توضیح سئو')->rows(3)->maxLength(500),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'legal' => 'قوانین و حریم خصوصی',
                        'shipping' => 'ارسال و تحویل',
                        'homepage' => 'صفحه اصلی',
                        default => 'صفحه عمومی',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'منتشرشده' : 'پیش‌نویس'),
                Tables\Columns\TextColumn::make('published_at')->label('انتشار')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(['draft' => 'پیش‌نویس', 'published' => 'منتشرشده']),
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع')
                    ->options([
                        'page' => 'صفحه عمومی',
                        'legal' => 'قوانین و حریم خصوصی',
                        'shipping' => 'ارسال و تحویل',
                        'homepage' => 'صفحه اصلی',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('مشاهده')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (BakeryContentPage $record): ?string => self::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (BakeryContentPage $record): bool => self::publicUrl($record) !== null),
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->requiresConfirmation()
                    ->visible(fn (BakeryContentPage $record): bool => ! self::isProtectedPage($record)),
            ])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc');
    }

    public static function isProtectedPage(BakeryContentPage $record): bool
    {
        return in_array($record->slug, self::PROTECTED_SLUGS, true);
    }

    public static function publicUrl(BakeryContentPage $record): ?string
    {
        $origin = trim((string) config('winimi.frontend_origins.0', ''));

        if ($origin === '' || trim((string) $record->slug) === '') {
            return null;
        }

        return rtrim($origin, '/').'/'.ltrim($record->slug, '/');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBakeryContentPages::route('/'),
        ];
    }
}
