<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AdminActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'adminActions';

    protected static ?string $title = 'تاریخچه تغییر وضعیت حساب';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->label('عملیات')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'disabled' => 'غیرفعال‌سازی',
                        'enabled' => 'فعال‌سازی',
                        default => 'تغییر مدیریتی',
                    }),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('مدیر انجام‌دهنده')
                    ->placeholder('مدیر حذف‌شده / سیستم'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('دلیل / یادداشت')
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('زمان')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
