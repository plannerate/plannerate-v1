<?php

use App\Models\Address;
use App\Models\Provider;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tests\TestCase;

uses(TestCase::class);

test('address switches connection by tenant context', function (): void {
    $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

    app()->forgetInstance($containerKey);
    expect((new Address)->getConnectionName())->toBe('landlord');

    app()->instance($containerKey, (object) ['id' => 'tenant-context']);
    expect((new Address)->getConnectionName())->toBeNull();

    app()->forgetInstance($containerKey);
});

test('address and addressable models expose polymorphic relations', function (): void {
    expect((new Address)->addressable())->toBeInstanceOf(MorphTo::class);
    expect((new Store)->addresses())->toBeInstanceOf(MorphMany::class);
    expect((new Provider)->addresses())->toBeInstanceOf(MorphMany::class);
    expect((new User)->addresses())->toBeInstanceOf(MorphMany::class);
    expect((new Tenant)->addresses())->toBeInstanceOf(MorphMany::class);
});
