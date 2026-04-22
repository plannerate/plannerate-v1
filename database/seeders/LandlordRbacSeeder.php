<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\PermissionName;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class LandlordRbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::all() as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        $landlordAdminRole = Role::findOrCreate('landlord-admin', 'web');
        $tenantAdminRole = Role::findOrCreate('tenant-admin', 'web');

        $landlordAdminRole->syncPermissions(PermissionName::all());
        $tenantAdminRole->syncPermissions([PermissionName::TENANT_DASHBOARD_VIEW]);

        setPermissionsTeamId($currentTeamId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
