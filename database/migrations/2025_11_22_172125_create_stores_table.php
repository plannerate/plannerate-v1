<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable()->index('stores_user_id_index');
            $table->char('client_id', 26)->nullable();
            $table->string('database')->nullable()->after('client_id')->comment('Nome do banco de dados dedicado (se multi-database)');
            $table->string('name')->nullable();
            $table->string('document')->nullable();
            $table->string('slug')->nullable()->unique('stores_slug_unique');
            $table->string('code')->nullable()->unique('stores_code_unique');
            $table->string('external_id')->nullable()->comment("ID externo da loja (empresa_id da API)");
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('integrate_id')->nullable()->comment("ID for integration with external systems");
            $table->string('description')->nullable();
            $table->string('map_image_path')->nullable()->comment('Path da imagem do mapa no storage');
            $table->json('map_regions')->nullable()->comment('JSON com regiões mapeadas [{id, x, y, width, height, shape, label, gondola_id}]');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            
            $table->index(['client_id', 'external_id'], 'stores_client_id_external_id_index');
            $table->unique(['client_id', 'code'], 'stores_client_id_code_unique');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
