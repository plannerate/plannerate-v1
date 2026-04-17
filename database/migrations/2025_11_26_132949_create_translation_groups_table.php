<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('raptor.tables.translation_groups', 'translation_groups');
        $tenantTable = config('raptor.tables.tenants', 'tenants');

        Schema::create($tableName, function (Blueprint $table) use ($tenantTable) {
            $table->ulid('id')->primary();

            $table->foreignUlid('tenant_id')
                ->nullable()
                ->constrained($tenantTable)
                ->nullOnDelete()
                ->comment('Tenant (NULL = tradução global)');

            $table->string('name')
                ->nullable()
                ->comment('Grupo das traduções (products, cart, etc)');

            $table->string('locale', 10)
                ->comment('Código do idioma (pt_BR, en, es, fr)');

            $table->timestamps();

            // Indexes para performance
            $table->index(['tenant_id', 'name', 'locale']);
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('raptor.tables.translation_groups', 'translation_groups'));
    }
};
