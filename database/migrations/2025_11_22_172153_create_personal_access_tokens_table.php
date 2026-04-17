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
     * Run the migrations - Cria tabela para Personal Access Tokens (Laravel Sanctum).
     */
    public function up(): void
    {
        $tableName = config('raptor.tables.personal_access_tokens', 'personal_access_tokens');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulidMorphs('tokenable');
            $table->text('name')->comment('Nome/descrição do token');
            $table->string('token', 64)->unique()->comment('Hash do token de acesso');
            $table->text('abilities')->nullable()->comment('Permissões/habilidades do token em JSON');
            $table->timestamp('last_used_at')->nullable()->comment('Data do último uso do token');
            $table->timestamp('expires_at')->nullable()->index()->comment('Data de expiração do token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('raptor.tables.personal_access_tokens', 'personal_access_tokens');
        Schema::dropIfExists($tableName);
    }
};
