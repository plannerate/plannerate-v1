<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Cria tabela de domínios dos tenants.
     */
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Relacionamento com tenant
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->onDelete('cascade')
                ->comment('ID do tenant proprietário do domínio');
                
            // Relacionamento polimórfico (opcional - para Client, Store, etc)
            $table->ulidMorphs('domainable');
            
            // Domínio (único no sistema)
            $table->string('domain')
                ->unique()
                ->comment('Domínio completo (ex: empresa.com.br, app.empresa.com)');
            
            // Indica se é o domínio principal do tenant
            $table->boolean('is_primary')
                ->default(false)
                ->comment('Define se é o domínio principal/padrão do tenant');
            
            $table->timestamps();
            
            // Índices para performance
            $table->index(['tenant_id', 'is_primary'], 'tenant_domains_tenant_primary_index');
            $table->index('domain', 'tenant_domains_domain_index'); // Busca rápida por domínio
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
