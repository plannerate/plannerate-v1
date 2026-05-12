<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Notifications\DatabaseNotification;

class DatabaseNotificationModel extends DatabaseNotification
{
    use HasUlids;

    public $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
            'read_at' => 'datetime',
        ];
    }
}
