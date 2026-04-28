<?php

namespace App\Services\Integrations\Auth;

enum AuthenticationType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKeyHeader = 'api_key_header';
    case ApiKeyQuery = 'api_key_query';
    case CustomHeaders = 'custom_headers';
}
