<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('role_tenant')) {
            return;
        }

        Schema::connection($this->connection)->create('role_tenant', function (Blueprint $table): void {
            $table->ulid('role_id');
            $table->ulid('tenant_id');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['role_id', 'tenant_id']);
        });

        $this->backfillExistingTenants();
    }

    /**
     * Vincula todas as roles de tenant existentes a todos os tenants existentes,
     * preservando o catálogo que cada tenant enxergava antes do recorte por pivot.
     */
    private function backfillExistingTenants(): void
    {
        $connection = DB::connection($this->connection);

        $tenantIds = $connection->table('tenants')->pluck('id');

        if ($tenantIds->isEmpty()) {
            return;
        }

        $roleIds = $connection->table('roles')
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', 'tenant')
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($tenantIds as $tenantId) {
            foreach ($roleIds as $roleId) {
                $rows[] = [
                    'role_id' => $roleId,
                    'tenant_id' => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $connection->table('role_tenant')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('role_tenant');
    }
};
