<?php

namespace App\Support;

use Illuminate\Support\Str;

class BroadcastPayload
{
    public static function shortenErrorMessage(?string $message, int $limit = 500): ?string
    {
        if ($message === null) {
            return null;
        }

        $normalized = trim($message);
        if ($normalized === '') {
            return null;
        }

        $safeLimit = max(4, $limit);

        return Str::limit($normalized, $safeLimit - 3);
    }
}
