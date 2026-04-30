<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'sysmo' => [
        'products' => [
            'required_flags' => [
                'cadastro_ativo',
                'ativo_na_empresa',
                'pertence_ao_mix',
            ],
            'required_flag_allowed_values' => [
                'cadastro_ativo' => ['S'],
                'ativo_na_empresa' => ['S'],
                'pertence_ao_mix' => ['S'],
            ],
        ],
        'tenants' => [
            'bruda' => [
                'auth_password' => env('SYSMO_BRUDA_AUTH_PASSWORD', ''),
            ],
            'franciosi' => [
                'auth_password' => env('SYSMO_FRANCIOSI_AUTH_PASSWORD', ''),
            ],
        ],
    ],

];
