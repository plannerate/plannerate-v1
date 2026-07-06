<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Code TTL
    |--------------------------------------------------------------------------
    |
    | How long (in days) the single-use "set your password" link issued when a
    | new tenant user is created (or resent by an admin) remains valid.
    | Independent of Fortify's own "forgot password" reset window
    | (config('auth.passwords.users.expire')).
    |
    */

    'code_ttl_days' => env('PASSWORD_SETUP_CODE_TTL_DAYS', 7),

];
