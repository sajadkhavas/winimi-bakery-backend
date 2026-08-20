<?php

namespace App\Filament\Resources\BakeryMediaAssetResource\Pages;

use App\Filament\Resources\BakeryMediaAssetResource;
use App\Models\BakeryMediaAsset;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ManageBakeryMediaAssets extends ManageRecords
{
    protected static string $resource =
        BakeryMediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulkUpload')
                ->label('آپلود گروهی تصاویر')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make(
                        'files'
                    )
                        ->label('تصاویر')
                        ->multiple()
                        ->image()
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ])
                        ->maxSize(12 * 1024)
                        ->maxFiles(50)
                        ->rules([
                            'dimensions:max_width=6000,max_height=6000',
                        ])
                        ->required()
                        ->helperText(
                            'تا ۵۰ تصویر در هر مرحله؛ هر فایل حداکثر ۱۲ مگابایت و ۶۰۰۰×۶۰۰۰ پیکسل.'
                        ),

                    Forms\Components\Select::make(
                        'usage'
                    )
                        ->label('کاربرد اولیه')
                        ->options([
                            BakeryMediaAsset::USAGE_UNASSIGNED => 'فعلاً تخصیص‌نیافته',

                            BakeryMediaAsset::USAGE_HERO => 'Hero / بنر',

                            BakeryMediaAsset::USAGE_BRAND => 'برند / Lifestyle',

                            BakeryMediaAsset::USAGE_CATEGORY => 'دسته‌بندی',
                        ])
                        ->default(
                            BakeryMediaAsset::USAGE_UNASSIGNED
                        )
                        ->required(),

                    Forms\Components\Textarea::make(
                        'notes'
                    )
                        ->label('یادداشت مشترک')
                        ->rows(2),
                ])
                ->action(
                    function (array $data): void {
                        $files = $data['files'] ?? [];

                        if (
                            ! is_array($files)
                            || $files === []
                        ) {
                            return;
                        }

                        DB::transaction(
                            function () use (
                                $files,
                                $data,
                            ): void {
                                foreach ($files as $file) {
                                    if (
                                        ! $file instanceof TemporaryUploadedFile
                                    ) {
                                        continue;
                                    }

                                    $originalName =
                                        $file->getClientOriginalName();

                                    $title = Str::of(
                                        pathinfo(
                                            $originalName,
                                            PATHINFO_FILENAME,
                                        )
                                    )
                                        ->replace([
                                            '_',
                                            '-',
                                        ], ' ')
                                        ->squish()
                                        ->limit(
                                            220,
                                            '',
                                        )
                                        ->toString();

                                    $asset =
                                        BakeryMediaAsset::query()
                                            ->create([
                                                'title' => $title !== ''
                                                        ? $title
                                                        : 'تصویر وینیمی',

                                                'usage' => $data['usage'],

                                                'status' => BakeryMediaAsset::STATUS_PENDING,

                                                'notes' => $data['notes']
                                                    ?? null,
                                            ]);

                                    try {
                                        $asset
                                            ->addMedia($file)
                                            ->usingName(
                                                $asset->title
                                            )
                                            ->toMediaCollection(
                                                'source'
                                            );
                                    } catch (Throwable $exception) {
                                        $asset->delete();

                                        throw $exception;
                                    }
                                }
                            },
                            3,
                        );
                    },
                )
                ->successNotificationTitle(
                    'تصاویر وارد کتابخانه رسانه شدند.'
                ),

            Actions\CreateAction::make()
                ->label('افزودن یک تصویر'),
        ];
    }
}
