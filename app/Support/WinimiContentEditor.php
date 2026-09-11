<?php

namespace App\Support;

use FilamentTiptapEditor\Enums\TiptapOutput;
use FilamentTiptapEditor\TiptapEditor;

final class WinimiContentEditor
{
    private const TOOLS = [
        'heading',
        'bullet-list',
        'ordered-list',
        'checked-list',
        'blockquote',
        'hr',
        'hurdle',
        '|',
        'bold',
        'italic',
        'strike',
        'underline',
        'superscript',
        'subscript',
        'lead',
        'small',
        'color',
        'highlight',
        'align-left',
        'align-center',
        'align-right',
        '|',
        'link',
        'media',
        'table',
        'details',
        'source',
    ];

    public static function make(string $name): TiptapEditor
    {
        return TiptapEditor::make($name)
            ->tools(self::TOOLS)
            ->output(TiptapOutput::Html)
            ->maxContentWidth('full')
            ->extraInputAttributes(['style' => 'min-height: 20rem;'])
            ->dehydrateStateUsing(
                fn (?string $state): ?string => SafeContentHtml::sanitize($state)
            );
    }

    /** @return list<string> */
    public static function tools(): array
    {
        return self::TOOLS;
    }

    public static function helperText(): string
    {
        return 'ویرایشگر یکپارچه WINIMI با HTML / Source امن فعال است. Source روی همان محتوای اصلی کار می‌کند؛ script، iframe، event handler و URL اجرایی هنگام ذخیره حذف می‌شوند. لینک و رسانه از Pickerهای مدیریت‌شده سایت استفاده می‌کنند.';
    }
}
