<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\CustomerAdminAction;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('disable')
                ->label('غیرفعال‌کردن حساب')
                ->color('danger')
                ->icon('heroicon-o-user-minus')
                ->visible(fn (): bool => (bool) $this->record->is_active)
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('دلیل غیرفعال‌سازی')
                        ->required()
                        ->minLength(5)
                        ->maxLength(1000)
                        ->rows(4)
                        ->helperText('این دلیل فقط در تاریخچه مدیریتی ثبت می‌شود و به مشتری نمایش داده نمی‌شود.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('غیرفعال‌کردن حساب مشتری')
                ->modalDescription('پس از تأیید، ورود و عملیات نیازمند حساب فعال برای این مشتری متوقف می‌شود. سفارش‌ها و سوابق قبلی حذف نمی‌شوند.')
                ->modalSubmitActionLabel('تأیید غیرفعال‌سازی')
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        $customer = $this->record->newQuery()->lockForUpdate()->findOrFail($this->record->getKey());
                        $customer->update(['is_active' => false]);

                        CustomerAdminAction::query()->create([
                            'customer_id' => $customer->getKey(),
                            'actor_user_id' => auth()->id(),
                            'action' => 'disabled',
                            'reason' => trim((string) $data['reason']),
                            'created_at' => now(),
                        ]);
                    }, 3);

                    $this->refreshFormData(['is_active']);
                    Notification::make()->success()->title('حساب مشتری غیرفعال و در تاریخچه ثبت شد.')->send();
                }),
            Actions\Action::make('enable')
                ->label('فعال‌کردن حساب')
                ->color('success')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => ! $this->record->is_active)
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('یادداشت فعال‌سازی')
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('اختیاری؛ برای ثبت علت یا مرجع پیگیری داخلی.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('فعال‌کردن دوباره حساب مشتری')
                ->modalDescription('دسترسی حساب دوباره فعال می‌شود و این تغییر در تاریخچه مدیریتی ثبت خواهد شد.')
                ->modalSubmitActionLabel('تأیید فعال‌سازی')
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        $customer = $this->record->newQuery()->lockForUpdate()->findOrFail($this->record->getKey());
                        $customer->update(['is_active' => true]);

                        CustomerAdminAction::query()->create([
                            'customer_id' => $customer->getKey(),
                            'actor_user_id' => auth()->id(),
                            'action' => 'enabled',
                            'reason' => filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null,
                            'created_at' => now(),
                        ]);
                    }, 3);

                    $this->refreshFormData(['is_active']);
                    Notification::make()->success()->title('حساب مشتری فعال و در تاریخچه ثبت شد.')->send();
                }),
        ];
    }
}
