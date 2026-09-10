<?php

return [

    'resources' => [
        'AutenticationLogResource' => \Tapp\FilamentAuthenticationLog\Resources\AuthenticationLogResource::class,
    ],

    'authenticable-resources' => [
        \App\Models\User::class,
    ],

    'authenticatable' => [
        'field-to-display' => null,
    ],

    'navigation' => [
        'authentication-log' => [
            // Logging remains enabled through the model trait, but the package's
            // untranslated raw navigation entry is intentionally hidden. Security
            // review remains a super-admin/system concern rather than employer UI.
            'register' => false,
            'sort' => 1,
            'icon' => 'heroicon-o-shield-check',
        ],
    ],

    'sort' => [
        'column' => 'login_at',
        'direction' => 'desc',
    ],
];
