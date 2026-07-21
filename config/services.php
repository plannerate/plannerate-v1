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

    'product_images' => [
        /*
         * Último recurso do ProductRepositoryImageResolver: gerar a arte com IA quando o
         * produto não existe no repositório da DO nem nos catálogos web.
         *
         * Default FALSE de propósito: geração de imagem no Gemini tem cota ZERO no free tier
         * (429 RESOURCE_EXHAUSTED, "limit: 0"), então sem billing habilitado no projeto do
         * Google Cloud toda tentativa é uma chamada HTTP condenada. Só ligue depois de
         * confirmar que a chave tem cota de imagem.
         */
        'ai_fallback' => env('PRODUCT_IMAGE_AI_FALLBACK', false),
    ],

    'cosmos' => [
        'token' => env('COSMOS_TOKEN'),
        'url' => env('COSMOS_URL', 'https://api.cosmos.bluesoft.com.br'),
    ],

    'metrics' => [
        'token' => env('METRICS_TOKEN'),
    ],

];
