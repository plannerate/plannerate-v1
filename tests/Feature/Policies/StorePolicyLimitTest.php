<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const STORE_POLICY_TENANT = 'tenant-store-policy-test';

function fakeStorePolicyTenant(array $settings = []): object
{
    return (object) [
        'id' => STORE_POLICY_TENANT,
        'settings' => $settings,
    ];
}

function insertStorePolicyRows(int $count, ?string $deletedAt = null): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::connection('landlord')->table('stores')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => STORE_POLICY_TENANT,
            'name' => fake()->company().' - Loja',
            'code' => Str::random(8),
            'slug' => Str::slug(fake()->company().'-'.Str::random(5)),
            'status' => 'published',
            'deleted_at' => $deletedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

afterEach(function () {
    DB::connection('landlord')->table('stores')->where('tenant_id', STORE_POLICY_TENANT)->delete();
});

it('hasReachedLimit returns false when no limit is configured', function () {
    app()->instance('current.tenant', fakeStorePolicyTenant());
    insertStorePolicyRows(10);

    $tenant = app('current.tenant');
    $count = \App\Models\Store::where('tenant_id', $tenant->id)->withoutTrashed()->count();

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_stores', $count))->toBeFalse();
});

it('hasReachedLimit returns false when below the limit', function () {
    app()->instance('current.tenant', fakeStorePolicyTenant(['limits' => ['max_stores' => 5]]));
    insertStorePolicyRows(2);

    $tenant = app('current.tenant');
    $count = \App\Models\Store::where('tenant_id', $tenant->id)->withoutTrashed()->count();

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_stores', $count))->toBeFalse();
});

it('hasReachedLimit returns true when limit is reached', function () {
    app()->instance('current.tenant', fakeStorePolicyTenant(['limits' => ['max_stores' => 3]]));
    insertStorePolicyRows(3);

    $tenant = app('current.tenant');
    $count = \App\Models\Store::where('tenant_id', $tenant->id)->withoutTrashed()->count();

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_stores', $count))->toBeTrue();
});

it('does not count soft-deleted stores toward the limit in the policy check', function () {
    app()->instance('current.tenant', fakeStorePolicyTenant(['limits' => ['max_stores' => 3]]));
    insertStorePolicyRows(3, now()->toDateTimeString()); // deleted
    insertStorePolicyRows(1); // active

    $tenant = app('current.tenant');
    $count = \App\Models\Store::where('tenant_id', $tenant->id)->withoutTrashed()->count();

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_stores', $count))->toBeFalse();
});
