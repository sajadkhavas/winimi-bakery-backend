<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebPushSubscriptionResource\Pages;
use App\Models\WebPushSubscription;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebPushSubscriptionResource extends Resource
{
    protected static ?string $model = WebPushSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'اعضای اعلان وب';

    protected static ?string $pluralModelLabel = 'اعضای اعلان وب';

    protected static ?string $navigationGroup = 'فروشگاه وینیمی';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('audience')
                    ->label('نوع عضو')
                    ->state(fn (WebPushSubscription $record): string => $record->customer_id ? 'مشتری' : 'مهمان')
                    ->badge(),
                Tables\Columns\TextColumn::make('customer.mobile')
                    ->label('مشتری')
                    ->formatStateUsing(fn (?string $state): string => self::mask($state))
                    ->placeholder('مهمان'),
                Tables\Columns\IconColumn::make('transactional_enabled')->label('تراکنشی')->boolean(),
                Tables\Columns\IconColumn::make('marketing_enabled')->label('عمومی')->boolean(),
                Tables\Columns\TextColumn::make('content_encoding')->label('Encoding')->badge(),
                Tables\Columns\TextColumn::make('last_seen_at')->label('آخرین مشاهده')->since()->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')->label('لغوشده')->since()->placeholder('فعال')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('عضویت')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('وضعیت')
                    ->queries(
                        true: fn ($query) => $query->whereNull('revoked_at'),
                        false: fn ($query) => $query->whereNotNull('revoked_at'),
                    ),
                Tables\Filters\TernaryFilter::make('customer_id')
                    ->label('نوع عضو')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('لغو امن')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (WebPushSubscription $record): bool => $record->revoked_at === null)
                    ->action(function (WebPushSubscription $record): void {
                        $record->update(['revoked_at' => now()]);
                        activity('web-push')
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->withProperties([
                                'action' => 'admin_revoke',
                                'audience' => $record->customer_id ? 'customer' : 'guest',
                            ])
                            ->log('Web push subscription revoked by operator');
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('last_seen_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'panel_user']) ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWebPushSubscriptions::route('/')];
    }

    private static function mask(?string $value): string
    {
        $value = (string) $value;

        return mb_strlen($value) > 6
            ? mb_substr($value, 0, 3).'****'.mb_substr($value, -3)
            : '****';
    }
}
