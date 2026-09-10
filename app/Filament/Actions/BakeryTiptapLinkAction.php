<?php

namespace App\Filament\Actions;

use App\Support\AdminInternalLinks;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Support\HtmlString;

final class BakeryTiptapLinkAction
{
    public static function make(): Action
    {
        return Action::make('filament_tiptap_link')
            ->modalWidth('lg')
            ->arguments([
                'href' => '',
                'id' => '',
                'hreflang' => '',
                'target' => '',
                'rel' => '',
                'referrerpolicy' => '',
                'as_button' => false,
                'button_theme' => '',
            ])
            ->mountUsing(function (ComponentContainer $form, array $arguments): void {
                $form->fill([
                    'internal_href' => array_key_exists((string) ($arguments['href'] ?? ''), AdminInternalLinks::options())
                        ? (string) $arguments['href']
                        : null,
                    'href' => $arguments['href'] ?? '',
                    'target' => $arguments['target'] ?? '',
                    'rel' => $arguments['rel'] ?? '',
                    'as_button' => $arguments['as_button'] ?? false,
                ]);
            })
            ->modalHeading(fn (array $arguments): string => filled($arguments['href'] ?? null)
                ? 'ویرایش لینک'
                : 'افزودن لینک')
            ->form([
                Select::make('internal_href')
                    ->label('انتخاب لینک داخلی')
                    ->options(fn (): array => AdminInternalLinks::options())
                    ->searchable()
                    ->preload()
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if (filled($state)) {
                            $set('href', $state);
                        }
                    })
                    ->helperText('مقاله، محصول، دسته یا صفحه منتشرشده را انتخاب کنید تا مسیر دقیق بدون خطای Slug درج شود.'),
                TextInput::make('href')
                    ->label('مسیر داخلی یا لینک خارجی')
                    ->required()
                    ->maxLength(2048)
                    ->rules(['regex:~^(?:https?://[^\s]+|/(?!/)[^\s]*|mailto:[^\s]+|tel:[+0-9() .-]+|#[A-Za-z][A-Za-z0-9_:\.-]*)$~i'])
                    ->helperText('مسیر داخلی با /، لینک خارجی با https://، یا لینک امن mailto:/tel:/# مجاز است.'),
                Select::make('target')
                    ->label('نحوه باز شدن')
                    ->options([
                        '' => 'همین صفحه',
                        '_blank' => 'صفحه جدید',
                    ])
                    ->default(''),
                TextInput::make('rel')
                    ->label('ویژگی rel')
                    ->placeholder('nofollow sponsored')
                    ->maxLength(160),
                Toggle::make('as_button')
                    ->label('نمایش به شکل دکمه')
                    ->default(false),
            ])
            ->action(function (TiptapEditor $component, array $data, array $arguments): void {
                $target = (string) ($data['target'] ?? '');
                $rel = trim((string) ($data['rel'] ?? ''));

                if ($target === '_blank' && ! str_contains(' '.$rel.' ', ' noopener ')) {
                    $rel = trim($rel.' noopener noreferrer');
                }

                $component->getLivewire()->dispatch(
                    event: 'insertFromAction',
                    type: 'link',
                    statePath: $component->getStatePath(),
                    href: $data['href'],
                    id: $arguments['id'] ?? '',
                    hreflang: $arguments['hreflang'] ?? '',
                    target: $target,
                    rel: $rel,
                    referrerpolicy: $arguments['referrerpolicy'] ?? '',
                    as_button: (bool) ($data['as_button'] ?? false),
                    button_theme: (bool) ($data['as_button'] ?? false) ? 'primary' : '',
                    coordinates: $arguments['coordinates'] ?? null,
                );

                $component->state($component->getState());
            })
            ->extraModalFooterActions(function (Action $action): array {
                if (($action->getArguments()['href'] ?? '') === '') {
                    return [];
                }

                return [
                    $action->makeModalSubmitAction('remove_link', [])
                        ->label('حذف لینک')
                        ->color('danger')
                        ->extraAttributes(function () use ($action): array {
                            return [
                                'x-on:click' => new HtmlString("\$dispatch('unset-link', {'statePath': '{$action->getComponent()->getStatePath()}'}); close()"),
                                'style' => 'margin-inline-start: auto;',
                            ];
                        }),
                ];
            });
    }
}
