<?php

use App\Support\Authorization\RbacType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('permissions')
            || ! Schema::connection($this->connection)->hasTable('roles')) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn('permissions', 'type')) {
            Schema::connection($this->connection)->table('permissions', function (Blueprint $table): void {
                $table->string('type', 50)->nullable()->after('id');
            });
        }

        if (! Schema::connection($this->connection)->hasColumn('roles', 'type')) {
            Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
                $table->string('type', 50)->nullable()->after('tenant_id');
            });
        }

        if (! Schema::connection($this->connection)->hasColumn('roles', 'system_name')) {
            Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
                $table->string('system_name')->nullable()->after('type');
            });
        }

        DB::connection($this->connection)
            ->table('permissions')
            ->whereNull('type')
            ->where('name', 'like', RbacType::LANDLORD.'.%')
            ->update(['type' => RbacType::LANDLORD]);

        DB::connection($this->connection)
            ->table('permissions')
            ->whereNull('type')
            ->where('name', 'like', RbacType::TENANT.'.%')
            ->update(['type' => RbacType::TENANT]);

        $untypedPermissions = DB::connection($this->connection)
            ->table('permissions')
            ->whereNull('type')
            ->pluck('name')
            ->all();

        if ($untypedPermissions !== []) {
            Log::warning('permissions without explicit type were defaulted to landlord', [
                'permissions' => $untypedPermissions,
            ]);

            DB::connection($this->connection)
                ->table('permissions')
                ->whereNull('type')
                ->update(['type' => RbacType::LANDLORD]);
        }

        DB::connection($this->connection)
            ->table('roles')
            ->whereNull('system_name')
            ->where('name', 'super-admin')
            ->update(['system_name' => 'super-admin']);

        DB::connection($this->connection)
            ->table('roles')
            ->whereNull('system_name')
            ->where('name', 'landlord-admin')
            ->update(['system_name' => 'landlord-admin']);

        DB::connection($this->connection)
            ->table('roles')
            ->whereNull('system_name')
            ->where('name', 'tenant-admin')
            ->update(['system_name' => 'tenant-admin']);

        DB::connection($this->connection)
            ->table('roles')
            ->whereNull('type')
            ->whereIn('system_name', ['super-admin', 'landlord-admin'])
            ->update(['type' => RbacType::LANDLORD]);

        DB::connection($this->connection)
            ->table('roles')
            ->whereNull('type')
            ->where('system_name', 'tenant-admin')
            ->update(['type' => RbacType::TENANT]);

        DB::connection($this->connection)
            ->table('roles')
            ->whereNull('type')
            ->where('name', 'like', RbacType::LANDLORD.'.%')
            ->update(['type' => RbacType::LANDLORD]);

        DB::connection($this->connection)
            ->table('roles')
            ->whereNull('type')
            ->where('name', 'like', RbacType::TENANT.'.%')
            ->update(['type' => RbacType::TENANT]);

        $roleTypeMap = DB::connection($this->connection)
            ->table('roles')
            ->leftJoin('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->leftJoin('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereNull('roles.type')
            ->groupBy('roles.id')
            ->selectRaw('roles.id, COUNT(DISTINCT permissions.type) as distinct_types, MAX(permissions.type) as inferred_type')
            ->get();

        foreach ($roleTypeMap as $row) {
            if ((int) $row->distinct_types === 1 && $row->inferred_type !== null) {
                DB::connection($this->connection)
                    ->table('roles')
                    ->where('id', $row->id)
                    ->update(['type' => $row->inferred_type]);
            }
        }

        $untypedRoles = DB::connection($this->connection)
            ->table('roles')
            ->whereNull('type')
            ->get(['id', 'name'])
            ->map(fn ($role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->all();

        if ($untypedRoles !== []) {
            Log::warning('roles without explicit type were defaulted to landlord', [
                'roles' => $untypedRoles,
            ]);

            DB::connection($this->connection)
                ->table('roles')
                ->whereNull('type')
                ->update(['type' => RbacType::LANDLORD]);
        }

        DB::connection($this->connection)->statement('ALTER TABLE `permissions` MODIFY `type` VARCHAR(50) NOT NULL');
        DB::connection($this->connection)->statement('ALTER TABLE `roles` MODIFY `type` VARCHAR(50) NOT NULL');

        try {
            DB::connection($this->connection)->statement('ALTER TABLE `permissions` DROP INDEX `permissions_name_guard_name_unique`');
        } catch (Throwable) {
            // index does not exist in this environment
        }

        try {
            DB::connection($this->connection)->statement('ALTER TABLE `roles` DROP INDEX `roles_team_name_guard_unique`');
        } catch (Throwable) {
            // index does not exist in this environment
        }

        try {
            DB::connection($this->connection)->statement('ALTER TABLE `permissions` DROP INDEX `permissions_guard_name_type_unique`');
        } catch (Throwable) {
            // index already missing
        }

        try {
            DB::connection($this->connection)->statement('ALTER TABLE `roles` DROP INDEX `roles_team_name_guard_type_unique`');
        } catch (Throwable) {
            // index already missing
        }

        Schema::connection($this->connection)->table('permissions', function (Blueprint $table): void {
            $table->unique(['guard_name', 'name', 'type'], 'permissions_guard_name_type_unique');
        });

        Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'guard_name', 'name', 'type'], 'roles_team_name_guard_type_unique');
        });

        if (! $this->hasUniqueIndex('roles', 'roles_system_name_unique')) {
            Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
                $table->unique('system_name', 'roles_system_name_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('permissions')
            || ! Schema::connection($this->connection)->hasTable('roles')) {
            return;
        }

        try {
            DB::connection($this->connection)->statement('ALTER TABLE `permissions` DROP INDEX `permissions_guard_name_type_unique`');
        } catch (Throwable) {
            // no-op
        }

        try {
            DB::connection($this->connection)->statement('ALTER TABLE `roles` DROP INDEX `roles_team_name_guard_type_unique`');
        } catch (Throwable) {
            // no-op
        }

        try {
            DB::connection($this->connection)->statement('ALTER TABLE `roles` DROP INDEX `roles_system_name_unique`');
        } catch (Throwable) {
            // no-op
        }

        if (Schema::connection($this->connection)->hasColumn('roles', 'system_name')) {
            Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
                $table->dropColumn('system_name');
            });
        }

        if (Schema::connection($this->connection)->hasColumn('roles', 'type')) {
            Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
                $table->dropColumn('type');
            });
        }

        if (Schema::connection($this->connection)->hasColumn('permissions', 'type')) {
            Schema::connection($this->connection)->table('permissions', function (Blueprint $table): void {
                $table->dropColumn('type');
            });
        }

        Schema::connection($this->connection)->table('permissions', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'name', 'guard_name'], 'roles_team_name_guard_unique');
        });
    }

    private function hasUniqueIndex(string $table, string $index): bool
    {
        $matches = DB::connection($this->connection)
            ->select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);

        return $matches !== [];
    }
};
