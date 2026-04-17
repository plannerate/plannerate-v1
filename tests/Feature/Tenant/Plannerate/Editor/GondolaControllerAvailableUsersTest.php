<?php

use App\Http\Controllers\Tenant\Plannerate\Editor\GondolaController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('returns available users as an array of id and name objects', function () {
    Cache::flush();

    config([
        'database.connections.landlord' => config('database.connections.sqlite'),
    ]);
    DB::purge('landlord');

    $tenantId = Str::ulid()->toString();
    $otherTenantId = Str::ulid()->toString();

    User::factory()->create([
        'tenant_id' => $tenantId,
        'name' => 'Bruno',
    ]);

    User::factory()->create([
        'tenant_id' => $tenantId,
        'name' => 'Ana',
    ]);

    User::factory()->create([
        'tenant_id' => $otherTenantId,
        'name' => 'Carlos',
    ]);

    $controller = app(GondolaController::class);

    $method = new \ReflectionMethod($controller, 'getAvailableUsers');
    $method->setAccessible(true);

    /** @var array<int, array{id: string, name: string}> $availableUsers */
    $availableUsers = $method->invoke($controller, $tenantId);

    expect($availableUsers)
        ->toBeArray()
        ->toHaveCount(2)
        ->and($availableUsers[0])
        ->toHaveKeys(['id', 'name'])
        ->and($availableUsers[0]['name'])
        ->toBe('Ana')
        ->and($availableUsers[1])
        ->toHaveKeys(['id', 'name'])
        ->and($availableUsers[1]['name'])
        ->toBe('Bruno');
});
