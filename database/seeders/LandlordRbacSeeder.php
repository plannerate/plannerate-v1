<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\PermissionName;
use App\Support\Authorization\RbacType;
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
                'type' => PermissionName::typeFor($permissionName) ?? RbacType::LANDLORD,
            ]);
        }

        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        $superAdminRole = Role::query()->firstOrCreate([
            'name' => 'Super Admin',
            'system_name' => 'super-admin',
            'guard_name' => 'web',
            'tenant_id' => null,
            'type' => RbacType::LANDLORD,
        ]);

        $landlordAdminRole = Role::query()->firstOrCreate([
            'name' => 'Landlord Admin',
            'system_name' => 'landlord-admin',
            'guard_name' => 'web',
            'tenant_id' => null,
            'type' => RbacType::LANDLORD,
        ]);

        $tenantAdminRole = Role::query()->firstOrCreate([
            'name' => 'Tenant Admin',
            'system_name' => 'tenant-admin',
            'guard_name' => 'web',
            'tenant_id' => null,
            'type' => RbacType::TENANT,
        ]);

        $superAdminRole->syncPermissions(PermissionName::all());
        $landlordAdminRole->syncPermissions(PermissionName::all());
        $tenantAdminRole->syncPermissions([
            PermissionName::TENANT_DASHBOARD_VIEW,
            PermissionName::TENANT_CATEGORIES_VIEW_ANY,
            PermissionName::TENANT_CATEGORIES_VIEW,
            PermissionName::TENANT_CATEGORIES_CREATE,
            PermissionName::TENANT_CATEGORIES_UPDATE,
            PermissionName::TENANT_CATEGORIES_DELETE,
            PermissionName::TENANT_PRODUCTS_VIEW_ANY,
            PermissionName::TENANT_PRODUCTS_VIEW,
            PermissionName::TENANT_PRODUCTS_CREATE,
            PermissionName::TENANT_PRODUCTS_UPDATE,
            PermissionName::TENANT_PRODUCTS_DELETE,
            PermissionName::TENANT_STORES_VIEW_ANY,
            PermissionName::TENANT_STORES_VIEW,
            PermissionName::TENANT_STORES_CREATE,
            PermissionName::TENANT_STORES_UPDATE,
            PermissionName::TENANT_STORES_DELETE,
            PermissionName::TENANT_CLUSTERS_VIEW_ANY,
            PermissionName::TENANT_CLUSTERS_VIEW,
            PermissionName::TENANT_CLUSTERS_CREATE,
            PermissionName::TENANT_CLUSTERS_UPDATE,
            PermissionName::TENANT_CLUSTERS_DELETE,
            PermissionName::TENANT_PROVIDERS_VIEW_ANY,
            PermissionName::TENANT_PROVIDERS_VIEW,
            PermissionName::TENANT_PROVIDERS_CREATE,
            PermissionName::TENANT_PROVIDERS_UPDATE,
            PermissionName::TENANT_PROVIDERS_DELETE,
            PermissionName::TENANT_PLANOGRAMS_VIEW_ANY,
            PermissionName::TENANT_PLANOGRAMS_VIEW,
            PermissionName::TENANT_PLANOGRAMS_CREATE,
            PermissionName::TENANT_PLANOGRAMS_UPDATE,
            PermissionName::TENANT_PLANOGRAMS_DELETE,
            PermissionName::TENANT_GONDOLAS_VIEW_ANY,
            PermissionName::TENANT_GONDOLAS_VIEW,
            PermissionName::TENANT_GONDOLAS_CREATE,
            PermissionName::TENANT_GONDOLAS_UPDATE,
            PermissionName::TENANT_GONDOLAS_DELETE,
            PermissionName::TENANT_KANBAN_VIEW_ANY,
            PermissionName::TENANT_KANBAN_EXECUTIONS_START,
            PermissionName::TENANT_KANBAN_EXECUTIONS_MOVE,
            PermissionName::TENANT_KANBAN_EXECUTIONS_MANAGE,
            PermissionName::TENANT_KANBAN_EXECUTIONS_RESTORE,
        ]);

        setPermissionsTeamId($currentTeamId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
