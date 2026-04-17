<?php

use App\Http\Controllers\Tenant\ClientController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const CLIENT_TEST_TENANT = 'tenant-client-limit-test';

function fakeClientTenant(array $settings = []): object
{
    return (object) [
        'id' => CLIENT_TEST_TENANT,
        'settings' => $settings,
    ];
}

function insertFakeClients(int $count, ?string $deletedAt = null): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::connection('landlord')->table('clients')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => CLIENT_TEST_TENANT,
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

function callClientBeforeCreate(array $settings = []): void
{
    app()->instance('current.tenant', fakeClientTenant($settings));

    $controller = app(ClientController::class);
    $method = new ReflectionMethod($controller, 'beforeCreate');
    $method->setAccessible(true);
    $method->invoke($controller, Request::create('/clients', 'POST'));
}

afterEach(function () {
    DB::connection('landlord')->table('clients')->where('tenant_id', CLIENT_TEST_TENANT)->delete();
});

it('allows client creation when no limit is configured', function () {
    insertFakeClients(10);

    expect(fn () => callClientBeforeCreate())->not->toThrow(ValidationException::class);
});

it('allows client creation when limit is zero', function () {
    insertFakeClients(10);

    expect(fn () => callClientBeforeCreate(['limits' => ['max_clients' => 0]]))->not->toThrow(ValidationException::class);
});

it('allows client creation when count is below the limit', function () {
    insertFakeClients(2);

    expect(fn () => callClientBeforeCreate(['limits' => ['max_clients' => 5]]))->not->toThrow(ValidationException::class);
});

it('blocks client creation when limit is reached', function () {
    insertFakeClients(3);

    expect(fn () => callClientBeforeCreate(['limits' => ['max_clients' => 3]]))->toThrow(ValidationException::class);
});

it('does not count soft-deleted clients toward the limit', function () {
    insertFakeClients(3, now()->toDateTimeString());
    insertFakeClients(2);

    // 3 deleted + 2 active → 2 active, limit is 3 → should allow
    expect(fn () => callClientBeforeCreate(['limits' => ['max_clients' => 3]]))->not->toThrow(ValidationException::class);
});
