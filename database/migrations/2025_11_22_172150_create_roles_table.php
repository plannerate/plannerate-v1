<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Callcocam\LaravelRaptor\Enums\RoleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Cria tabela de roles (papéis/funções).
     */
    public function up(): void
    {
        $tableName = config('raptor.tables.roles', 'roles');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->comment('Nome do papel/função (ex: Admin, Editor)');
            $table->string('slug')->unique()->comment('Identificador único do papel');
            $table->text('description')->nullable()->comment('Descrição do papel e suas responsabilidades');
            $table->enum('status', array_column(RoleStatus::cases(), 'value'))
                ->default(RoleStatus::Draft->value)
                ->comment('Status do papel (ativo, inativo, etc.)');
            $table->boolean('special')->default(false)->nullable()->comment('Papel especial com permissões all-access ou no-access');
            $table->foreignUlid('tenant_id')->nullable()->constrained(config('raptor.tables.tenants', 'tenants'))->nullOnDelete()->comment('Tenant ao qual o papel pertence');
            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('raptor.tables.roles', 'roles');
        Schema::dropIfExists($tableName);
    }
};