<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TranslationResource\Pages;
use App\Models\Translation;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationLabel = 'مدیریت ترجمه‌ها';

    protected static ?string $modelLabel = 'ترجمه';

    protected static ?string $pluralModelLabel = 'ترجمه‌ها';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('شناسه ترجمه')
                ->description('Group و Key بخشی از قرارداد فنی هستند؛ تغییر آن‌ها فقط برای مدیر ارشد مجاز است.')
                ->schema([
                    TextInput::make('group')->label('گروه')->required()->placeholder('مثال: messages'),
                    TextInput::make('key')->label('کلید')->required()->placeholder('مثال: submit'),
                ])->columns(2),
            Section::make('متن ترجمه‌ها')->schema([
                TextInput::make('value.fa')->label('فارسی')->required(),
                TextInput::make('value.en')->label('انگلیسی'),
                TextInput::make('value.ar')->label('عربی'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->label('گروه')->badge()->color('primary')->searchable(),
                Tables\Columns\TextColumn::make('key')->label('کلید')->searchable(),
                Tables\Columns\TextColumn::make('value.fa')->label('فارسی')->limit(40),
                Tables\Columns\TextColumn::make('value.en')->label('انگلیسی')->limit(40)->default('—'),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y/m/d')->sortable(),
            ])
            ->defaultSort('group')
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('گروه')
                    ->options(fn (): array => Translation::query()->distinct()->pluck('group', 'group')->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()->label('حذف')->requiresConfirmation(),
            ])
            ->bulkActions([]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTranslations::route('/'),
            'create' => Pages\CreateTranslation::route('/create'),
            'edit' => Pages\EditTranslation::route('/{record}/edit'),
        ];
    }
}
