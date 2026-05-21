<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->create('planogram_product_rules', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            // mandatory = entra sempre; blocked = nunca entra
            $table->string('type', 10); // 'mandatory' | 'blocked'
            // Pelo menos um dos três alvos deve ser preenchido (validado no model)
            $table->char('product_id', 26)->nullable();
            $table->string('brand', 255)->nullable();
            $table->char('subcategory_id', 26)->nullable();
            // Motivo legível para auditoria
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'type', 'product_id']);
            $table->index(['tenant_id', 'type', 'subcategory_id']);
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('planogram_product_rules');
    }
};
