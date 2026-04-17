<?php

use App\Services\TenantLimitService;
use Illuminate\Validation\ValidationException;

function makeFakeTenant(array $settings = []): object
{
    return (object) [
        'id' => 'tenant-test-id',
        'settings' => $settings,
    ];
}

beforeEach(function () {
    $this->service = app(TenantLimitService::class);
});

it('does not throw when limit is zero (sem limite)', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_clients' => 0]]));

    expect(fn () => $this->service->enforce('max_clients', 99, 'clientes'))->not->toThrow(ValidationException::class);
});

it('does not throw when limit is not set', function () {
    app()->instance('current.tenant', makeFakeTenant());

    expect(fn () => $this->service->enforce('max_clients', 99, 'clientes'))->not->toThrow(ValidationException::class);
});

it('does not throw when count is below the limit', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_clients' => 5]]));

    expect(fn () => $this->service->enforce('max_clients', 4, 'clientes'))->not->toThrow(ValidationException::class);
});

it('throws ValidationException when count reaches the limit', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_clients' => 3]]));

    expect(fn () => $this->service->enforce('max_clients', 3, 'clientes'))
        ->toThrow(ValidationException::class);
});

it('throws ValidationException when count exceeds the limit', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_clients' => 2]]));

    expect(fn () => $this->service->enforce('max_clients', 5, 'clientes'))
        ->toThrow(ValidationException::class);
});

it('includes the limit value in the error message', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_stores' => 10]]));

    try {
        $this->service->enforce('max_stores', 10, 'lojas');
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors()['name'][0])->toContain('10')->toContain('lojas');
    }
});

it('returns the configured limit via getLimit', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_users' => 25]]));

    expect($this->service->getLimit('max_users'))->toBe(25);
});

it('returns zero via getLimit when not configured', function () {
    app()->instance('current.tenant', makeFakeTenant());

    expect($this->service->getLimit('max_users'))->toBe(0);
});

it('hasReachedLimit returns false when limit is zero', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_users' => 0]]));

    expect($this->service->hasReachedLimit('max_users', 999))->toBeFalse();
});

it('hasReachedLimit returns true when count equals the limit', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_users' => 5]]));

    expect($this->service->hasReachedLimit('max_users', 5))->toBeTrue();
});

it('hasReachedLimit returns false when count is below the limit', function () {
    app()->instance('current.tenant', makeFakeTenant(['limits' => ['max_users' => 5]]));

    expect($this->service->hasReachedLimit('max_users', 4))->toBeFalse();
});
