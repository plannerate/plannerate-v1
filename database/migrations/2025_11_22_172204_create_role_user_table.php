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
     * Run the migrations - Cria tabela pivot role_user (relacionamento muitos-para-muitos).
     */
    public function up(): void
    {
        $tableName = config('raptor.tables.role_user', 'role_user');

        Schema::create($tableName, function (Blueprint $table) {
            $table->foreignUlid('role_id')->constrained(config('raptor.tables.roles', 'roles'))->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained(config('raptor.tables.users', 'users'))->onDelete('cascade');
            $table->primary(['role_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('raptor.tables.role_user', 'role_user');
        Schema::dropIfExists($tableName);
    }
};