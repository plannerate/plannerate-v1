<?php

return [
    'table' => env('LOGIN_AS_TABLE', 'login_as_tokens'),
    'ttl_seconds' => (int) env('LOGIN_AS_TOKEN_TTL_SECONDS', 90),
    'token_length' => (int) env('LOGIN_AS_TOKEN_LENGTH', 64),
];

