<?php

use Callcocam\LaravelRaptor\Services\PermissionCatalogService;

it('keeps action aliases canonical in permission catalog', function () {
    $catalog = app(PermissionCatalogService::class);
    $aliases = $catalog->getActionAliases();

    expect($aliases['store'] ?? null)->toBe('create');
    expect($aliases['update'] ?? null)->toBe('edit');
    expect($aliases['destroy'] ?? null)->toBe('delete');
    expect($aliases['execute'] ?? null)->toBe('create');
    expect($aliases['viewAny'] ?? null)->toBe('index');
});

it('does not generate redundant alias actions in expected permissions', function () {
    $permissions = app(PermissionCatalogService::class)->expectedPermissions(null, true);
    $slugs = $permissions->pluck('slug');

    expect($slugs->contains(fn (string $slug) => str_ends_with($slug, '.store')))->toBeFalse();
    expect($slugs->contains(fn (string $slug) => str_ends_with($slug, '.update')))->toBeFalse();
    expect($slugs->contains(fn (string $slug) => str_ends_with($slug, '.destroy')))->toBeFalse();
    expect($slugs->contains(fn (string $slug) => str_ends_with($slug, '.execute')))->toBeFalse();
    expect($slugs->contains(fn (string $slug) => str_ends_with($slug, '.viewAny')))->toBeFalse();
});

it('ignores composite resources that include ignored tokens', function () {
    $catalog = app(PermissionCatalogService::class);

    expect($catalog->shouldIgnorePermissionSlug('tenant.api.sections.index'))->toBeTrue();
    expect($catalog->shouldIgnorePermissionSlug('tenant.api-sections.index'))->toBeTrue();
    expect($catalog->shouldIgnorePermissionSlug('tenant.api-shelves.edit'))->toBeTrue();
});
