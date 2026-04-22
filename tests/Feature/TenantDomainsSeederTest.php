<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantDomainsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('tenant domains seeder creates two tenants and one admin user in each tenant database', function () {
    DB::connection('landlord')->statement('DROP DATABASE IF EXISTS `tenant_alfa`');
    DB::connection('landlord')->statement('DROP DATABASE IF EXISTS `tenant_coperdia`');

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => TenantDomainsSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => TenantDomainsSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $tenants = Tenant::query()->with('primaryDomain')->orderBy('slug')->get();

    expect($tenants)->toHaveCount(2);
    expect($tenants->pluck('primaryDomain.host')->all())->toBe([
        'alfa.plannerate-v1.test',
        'coperdia.plannerate-v1.test',
    ]);

    foreach ($tenants as $tenant) {
        $tenant->execute(function (Tenant $currentTenant): void {
            $host = $currentTenant->primaryDomain()->value('host');

            expect(User::query()->where('email', 'admin@'.$host)->count())->toBe(1);
        });
    }
});
