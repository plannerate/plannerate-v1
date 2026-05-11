<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, string $id): bool {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('tenant.{tenantId}', function ($user, string $tenantId): bool {
    return (string) Tenant::current()?->getKey() === (string) $tenantId;
});
