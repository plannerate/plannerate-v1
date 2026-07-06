<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Code TTL
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) the single-use handoff code issued from the
    | landlord panel remains valid before it must be consumed.
    |
    */

    'code_ttl_minutes' => env('IMPERSONATION_CODE_TTL_MINUTES', 2),

    /*
    |--------------------------------------------------------------------------
    | Session TTL
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) an active impersonation session is allowed to
    | last once consumed, enforced on every request by
    | EnsureValidImpersonationSession.
    |
    */

    'session_ttl_minutes' => env('IMPERSONATION_SESSION_TTL_MINUTES', 30),

];
