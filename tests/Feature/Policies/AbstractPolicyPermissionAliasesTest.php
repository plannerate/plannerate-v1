<?php

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use Callcocam\LaravelRaptor\Enums\PermissionStatus;

function ensurePolicyPermission(string $slug): void
{
    $permissionModel = config('raptor.shinobi.models.permission');

    if ($permissionModel::query()->where('slug', $slug)->exists()) {
        return;
    }

    $permissionModel::query()->create([
        'name' => $slug,
        'slug' => $slug,
        'description' => $slug,
        'context' => 'tenant',
        'status' => PermissionStatus::Published->value,
        'tenant_id' => null,
    ]);
}

it('supports create/store and edit/update aliases in abstract policy', function () {
    $policy = new class extends ProductPolicy
    {
        protected ?string $context = 'tenant';
    };

    ensurePolicyPermission('tenant.products.create');
    ensurePolicyPermission('tenant.products.edit');

    $userWithCreate = User::factory()->create();
    $userWithCreate->givePermissionTo('tenant.products.create');

    $userWithEdit = User::factory()->create();
    $userWithEdit->givePermissionTo('tenant.products.edit');

    expect($policy->store($userWithCreate))->toBeTrue();
    expect($policy->update($userWithEdit, new Product))->toBeTrue();
});

it('supports destroy as fallback for delete permission checks', function () {
    $policy = new class extends ProductPolicy
    {
        protected ?string $context = 'tenant';
    };

    ensurePolicyPermission('tenant.products.destroy');

    $userWithDestroy = User::factory()->create();
    $userWithDestroy->givePermissionTo('tenant.products.destroy');

    expect($policy->delete($userWithDestroy, new Product))->toBeTrue();
});
