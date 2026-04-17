<?php

use App\Models\User;
use Callcocam\LaravelRaptor\Policies\UserPolicy;

function fakeUserPolicyTenant(array $settings = []): object
{
    return (object) [
        'id' => 'tenant-user-policy-test',
        'settings' => $settings,
    ];
}

it('create returns false when parent permission check fails', function () {
    app()->instance('current.tenant', fakeUserPolicyTenant(['limits' => ['max_users' => 100]]));

    $user = User::factory()->create();
    $policy = new UserPolicy;

    // Without any permission granted, parent::create returns false → policy returns false regardless of limit
    expect($policy->create($user))->toBeFalse();
});

it('hasReachedLimit returns false when max_users is not set', function () {
    app()->instance('current.tenant', fakeUserPolicyTenant());

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_users', 999))->toBeFalse();
});

it('hasReachedLimit returns true when max_users limit is reached', function () {
    app()->instance('current.tenant', fakeUserPolicyTenant(['limits' => ['max_users' => 5]]));

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_users', 5))->toBeTrue();
});

it('hasReachedLimit returns false when below max_users limit', function () {
    app()->instance('current.tenant', fakeUserPolicyTenant(['limits' => ['max_users' => 10]]));

    expect(app(\App\Services\TenantLimitService::class)->hasReachedLimit('max_users', 4))->toBeFalse();
});

it('create skips limit check when current.tenant is not bound', function () {
    // Ensure current.tenant is not bound
    $policy = new UserPolicy;
    $user = User::factory()->create();

    // Without tenant bound and without permissions, parent check fails → false
    // The important thing is no exception is thrown from the unbound tenant
    expect(fn () => $policy->create($user))->not->toThrow(\Exception::class);
});
