<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Callcocam\LaravelRaptor\Enums\TenantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Cria tabela de tenants (clientes/organizações).
     */
    public function up(): void
    {
        $tableName = config('raptor.tables.tenants', 'tenants');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->comment('Nome do tenant/organização');
            $table->string('slug')->unique()->comment('Identificador único amigável para URLs');
            
            // Identificadores de acesso
            $table->string('subdomain')->nullable()->unique()->comment('Subdomínio do tenant (ex: empresa.example.com)');
            $table->string('domain')->nullable()->comment('Domínio base do tenant (ex: example.com)');
            
            // Configurações de banco de dados (para multi-database)
            $table->string('database')->unique()->nullable()->comment('Nome do banco de dados dedicado (se multi-database)');
            $table->string('prefix')->unique()->nullable()->comment('Prefixo de tabelas (se multi-schema)');
            
            // Informações de contato
            $table->string('email')->nullable()->comment('Email de contato do tenant');
            $table->string('phone')->nullable()->comment('Telefone de contato do tenant');
            $table->string('document')->nullable()->comment('Documento (CNPJ/CPF) do tenant');
            
            // Personalização
            $table->string('logo')->nullable()->comment('URL ou path do logo do tenant');
            $table->json('settings')->nullable()->comment('Configurações personalizadas do tenant');
            
            // Status e controle
            $table->enum('status', array_column(TenantStatus::cases(), 'value'))
                ->default(TenantStatus::Published->value)
                ->comment('Status do tenant (ativo, inativo, suspenso, etc.)');
            $table->boolean('is_primary')->default(false)->comment('Indica se é o tenant principal/master');
            
            $table->text('description')->nullable()->comment('Descrição ou observações sobre o tenant');
            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('raptor.tables.tenants', 'tenants');
        Schema::dropIfExists($tableName);
    }
}; 