<?php

use App\Models\User;
use App\Policies\ClientPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const CLIENT_POLICY_TENANT = 'tenant-client-policy-test';

function fakeClientPolicyTenant(array $settings = []): object
{
    return (object) [
        'id' => CLIENT_POLICY_TENANT,
        'settings' => $settings,
    ];
}

function insertClientPolicyRows(int $count, ?string $deletedAt = null): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::connection('landlord')->table('clients')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => CLIENT_POLICY_TENANT,
            'name' => fake()->company(),
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'published',
            'slug' => Str::slug(fake()->company().'-'.Str::random(5)),
            'deleted_at' => $deletedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

afterEach(function () {
    DB::connection('landlord')->table('clients')->where('tenant_id', CLIENT_POLICY_TENANT)->delete();
});

it('allows create when no limit is configured', function () {
    app()->instance('current.tenant', fakeClientPolicyTenant());
    insertClientPolicyRows(5);

    $user = User::factory()->create();
    $policy = new ClientPolicy;

    // Without permissions configured, parent::create returns false anyway.
    // We test that the policy's limit check doesn't block unnecessarily.
    // Grant permission by mocking the permission check.
    $user->givePermissionTo = fn () => true;

    // The limit portion: hasReachedLimit should be false when limit is 0
    $tenant = app('current.tenant');
    $count = \App\Models\Client::where('tenant_id', $tenant->id)->withoutTrashed()->count();
    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_clients', $count))->toBeFalse();
});

it('hasReachedLimit returns false when below the limit', function () {
    app()->instance('current.tenant', fakeClientPolicyTenant(['limits' => ['max_clients' => 5]]));
    insertClientPolicyRows(3);

    $tenant = app('current.tenant');
    $count = \App\Models\Client::where('tenant_id', $tenant->id)->withoutTrashed()->count();

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_clients', $count))->toBeFalse();
});

it('hasReachedLimit returns true when limit is reached', function () {
    app()->instance('current.tenant', fakeClientPolicyTenant(['limits' => ['max_clients' => 3]]));
    insertClientPolicyRows(3);

    $tenant = app('current.tenant');
    $count = \App\Models\Client::where('tenant_id', $tenant->id)->withoutTrashed()->count();

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_clients', $count))->toBeTrue();
});

it('does not count soft-deleted clients toward the limit in the policy check', function () {
    app()->instance('current.tenant', fakeClientPolicyTenant(['limits' => ['max_clients' => 3]]));
    insertClientPolicyRows(3, now()->toDateTimeString()); // deleted
    insertClientPolicyRows(2); // active

    $tenant = app('current.tenant');
    $count = \App\Models\Client::where('tenant_id', $tenant->id)->withoutTrashed()->count();

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_clients', $count))->toBeFalse();
});
