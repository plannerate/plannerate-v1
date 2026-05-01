<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = (string) ($columnNames['role_pivot_key'] ?? 'role_id');
        $pivotPermission = (string) ($columnNames['permission_pivot_key'] ?? 'permission_id');
        $teamForeignKey = (string) ($columnNames['team_foreign_key'] ?? 'tenant_id');

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::connection($this->connection)->create($tableNames['permissions'], static function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type', 50);
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['guard_name', 'name', 'type'], 'permissions_guard_name_type_unique');
        });

        Schema::connection($this->connection)->create($tableNames['roles'], static function (Blueprint $table) use ($teamForeignKey) {
            $table->ulid('id')->primary();
            $table->ulid($teamForeignKey)->nullable()->index();
            $table->string('type', 50);
            $table->string('system_name')->nullable()->unique();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->softDeletes();
            $table->unique([$teamForeignKey, 'guard_name', 'name', 'type'], 'roles_team_name_guard_type_unique');
        });

        Schema::connection($this->connection)->create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $teamForeignKey, $pivotPermission) {
            $table->ulid($pivotPermission);
            $table->ulid($teamForeignKey)->nullable()->index();
            $table->string('model_type');
            $table->ulid('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->unique([$teamForeignKey, $pivotPermission, 'model_id', 'model_type'], 'model_has_permissions_unique');
        });

        Schema::connection($this->connection)->create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $teamForeignKey, $pivotRole) {
            $table->ulid($pivotRole);
            $table->ulid($teamForeignKey)->nullable()->index();
            $table->string('model_type');
            $table->ulid('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            $table->unique([$teamForeignKey, $pivotRole, 'model_id', 'model_type'], 'model_has_roles_unique');
        });

        Schema::connection($this->connection)->create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->ulid($pivotPermission);
            $table->ulid($pivotRole);

            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');

        Schema::connection($this->connection)->dropIfExists($tableNames['role_has_permissions']);
        Schema::connection($this->connection)->dropIfExists($tableNames['model_has_roles']);
        Schema::connection($this->connection)->dropIfExists($tableNames['model_has_permissions']);
        Schema::connection($this->connection)->dropIfExists($tableNames['roles']);
        Schema::connection($this->connection)->dropIfExists($tableNames['permissions']);
    }
};
