<?php

use App\Http\Controllers\Tenant\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const STORE_TEST_TENANT = 'tenant-store-limit-test';

function fakeStoreTenant(array $settings = []): object
{
    return (object) [
        'id' => STORE_TEST_TENANT,
        'settings' => $settings,
    ];
}

function insertFakeStores(int $count, ?string $deletedAt = null): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::connection('landlord')->table('stores')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => STORE_TEST_TENANT,
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

function callStoreBeforeCreate(array $settings = []): void
{
    app()->instance('current.tenant', fakeStoreTenant($settings));

    $controller = app(StoreController::class);
    $method = new ReflectionMethod($controller, 'beforeCreate');
    $method->setAccessible(true);
    $method->invoke($controller, Request::create('/stores', 'POST'));
}

afterEach(function () {
    DB::connection('landlord')->table('stores')->where('tenant_id', STORE_TEST_TENANT)->delete();
});

it('allows store creation when no limit is configured', function () {
    insertFakeStores(10);

    expect(fn () => callStoreBeforeCreate())->not->toThrow(ValidationException::class);
});

it('allows store creation when limit is zero', function () {
    insertFakeStores(10);

    expect(fn () => callStoreBeforeCreate(['limits' => ['max_stores' => 0]]))->not->toThrow(ValidationException::class);
});

it('allows store creation when count is below the limit', function () {
    insertFakeStores(2);

    expect(fn () => callStoreBeforeCreate(['limits' => ['max_stores' => 5]]))->not->toThrow(ValidationException::class);
});

it('blocks store creation when limit is reached', function () {
    insertFakeStores(3);

    expect(fn () => callStoreBeforeCreate(['limits' => ['max_stores' => 3]]))->toThrow(ValidationException::class);
});

it('does not count soft-deleted stores toward the limit', function () {
    insertFakeStores(3, now()->toDateTimeString());
    insertFakeStores(1);

    // 3 deleted + 1 active → 1 active, limit is 3 → should allow
    expect(fn () => callStoreBeforeCreate(['limits' => ['max_stores' => 3]]))->not->toThrow(ValidationException::class);
});
