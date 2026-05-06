<?php

return [
    'api_token' => env('CLOUDFLARE_API_TOKEN', ''),
    'zone_id' => env('CLOUDFLARE_ZONE_ID', ''),
    'cname_target' => env('CLOUDFLARE_CNAME_TARGET', ''),
    'base_uri' => env('CLOUDFLARE_BASE_URI', 'https://api.cloudflare.com/client/v4'),
    'timeout' => env('CLOUDFLARE_TIMEOUT', 30),
];
