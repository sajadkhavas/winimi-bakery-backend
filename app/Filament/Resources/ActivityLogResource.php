<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'تاریخچه فعالیت‌ها';

    protected static ?string $modelLabel = 'فعالیت';

    protected static ?string $pluralModelLabel = 'تاریخچه فعالیت‌ها';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable()->width(60),
                Tables\Columns\TextColumn::make('log_name')
                    ->label('نوع')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'auth' => 'info',
                        'default' => 'gray',
                        default => 'primary',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('رویداد')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'created' => 'ایجاد',
                        'updated' => 'ویرایش',
                        'deleted' => 'حذف',
                        default => (string) $state,
                    })
                    ->color(fn ($state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')->label('توضیحات')->limit(60),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('موضوع')
                    ->formatStateUsing(fn ($state): string => $state ? class_basename($state) : '—')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('causer.name')->label('مدیر / عامل')->default('—'),
                Tables\Columns\TextColumn::make('created_at')->label('زمان')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('رویداد')
                    ->options([
                        'created' => 'ایجاد',
                        'updated' => 'ویرایش',
                        'deleted' => 'حذف',
                    ]),
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('نوع تاریخچه')
                    ->options([
                        'default' => 'عمومی',
                        'auth' => 'احراز هویت',
                    ]),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('مشاهده')])
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

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLog::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
