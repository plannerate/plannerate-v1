<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Callcocam\LaravelRaptor\Enums\PermissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Callcocam\LaravelRaptor\Enums\Menu\ContextEnum;

return new class extends Migration
{
    /**
     * Run the migrations - Cria tabela de permissões.
     */
    public function up(): void
    {
        $tableName = config('raptor.tables.permissions', 'permissions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->comment('Nome da permissão (ex: Criar Usuários)');
            $table->string('slug')->unique()->comment('Identificador único da permissão (ex: users.create)');
            $table->text('description')->nullable()->comment('Descrição do que a permissão permite fazer');           
            $table->enum('context', array_column(ContextEnum::cases(), 'value'))
                ->default(ContextEnum::LANDLORD->value)
                ->comment('Contexto da permissão (landlord, tenant, etc.)');
            $table->enum('status', array_column(PermissionStatus::cases(), 'value'))
                ->default(PermissionStatus::Draft->value)
                ->comment('Status da permissão (ativa, inativa, etc.)');
            $table->foreignUlid('tenant_id')->nullable()->constrained(config('raptor.tables.tenants', 'tenants'))->nullOnDelete()->comment('Tenant ao qual a permissão pertence');
            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('raptor.tables.permissions', 'permissions');
        Schema::dropIfExists($tableName);
    }
};