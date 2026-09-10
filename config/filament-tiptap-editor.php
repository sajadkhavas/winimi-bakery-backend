<?php

return [
    'direction' => 'rtl',
    'max_content_width' => '5xl',
    'disable_stylesheet' => false,
    'disable_link_as_button' => false,

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | Profiles determine which tools are available for the toolbar.
    | Raw source/code/embed tools are intentionally excluded from the Winimi
    | default profile. Individual resources may further reduce this list.
    |
    */
    'profiles' => [
        'default' => [
            'heading', 'bullet-list', 'ordered-list', 'checked-list', 'blockquote', 'hr', '|',
            'bold', 'italic', 'strike', 'underline', 'superscript', 'subscript', 'lead', 'small', 'color', 'highlight', 'align-left', 'align-center', 'align-right', '|',
            'link', 'media', 'table', 'details',
        ],
        'simple' => ['heading', 'hr', 'bullet-list', 'ordered-list', 'checked-list', '|', 'bold', 'italic', 'lead', 'small', 'color', 'highlight', 'align-left', 'align-center', 'align-right', '|', 'link', 'media'],
        'minimal' => ['bold', 'italic', 'link', 'bullet-list', 'ordered-list'],
        'none' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    |
    | Link/media insertion and media editing are routed through Winimi's
    | managed pickers so the Bakery Media Library remains the source of truth.
    |
    */
    'media_action' => App\Filament\Actions\BakeryTiptapMediaAction::class,
    'edit_media_action' => App\Filament\Actions\BakeryTiptapEditMediaAction::class,
    'link_action' => App\Filament\Actions\BakeryTiptapLinkAction::class,
    'grid_builder_action' => FilamentTiptapEditor\Actions\GridBuilderAction::class,
    'oembed_action' => FilamentTiptapEditor\Actions\OEmbedAction::class,

    /*
    |--------------------------------------------------------------------------
    | Output format
    |--------------------------------------------------------------------------
    */
    'output' => FilamentTiptapEditor\Enums\TiptapOutput::Html,

    /*
    |--------------------------------------------------------------------------
    | Media Uploader
    |--------------------------------------------------------------------------
    |
    | Kept for package compatibility. Winimi's active media actions above do
    | not expose this direct uploader; new images must enter through the
    | Bakery Media Library and its optimized conversion pipeline.
    |
    */
    'accepted_file_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'disk' => 'public',
    'directory' => 'images',
    'visibility' => 'public',
    'preserve_file_names' => false,
    'max_file_size' => 2042,
    'min_file_size' => 0,
    'image_resize_mode' => null,
    'image_crop_aspect_ratio' => null,
    'image_resize_target_width' => null,
    'image_resize_target_height' => null,
    'use_relative_paths' => false,

    /*
    |--------------------------------------------------------------------------
    | Menus
    |--------------------------------------------------------------------------
    */
    'disable_floating_menus' => false,
    'disable_bubble_menus' => false,
    'disable_toolbar_menus' => false,

    'bubble_menu_tools' => ['bold', 'italic', 'strike', 'underline', 'superscript', 'subscript', 'lead', 'small', 'link'],
    'floating_menu_tools' => ['media', 'details', 'table'],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    */
    'extensions_script' => null,
    'extensions_styles' => null,
    'extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | Preset Colors
    |--------------------------------------------------------------------------
    */
    'preset_colors' => [
        'winimi-green' => '#27390c',
        'winimi-cream' => '#f5f0e6',
        'winimi-ink' => '#1f2418',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protocols
    |--------------------------------------------------------------------------
    */
    'link_protocols' => ['mailto', 'tel'],
];
