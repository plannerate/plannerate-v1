<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Callcocam\LaravelRaptor\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Cria tabela de usuários e tabelas relacionadas.
     */
    public function up(): void
    {
        $tableName = config('raptor.tables.users', 'users');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable()->comment('Tenant/organização do usuário');
            $table->string('name')->comment('Nome completo do usuário');
            $table->string('email')->unique()->comment('Email para login e contato');
            $table->string('slug')->unique()->comment('Identificador único amigável para URLs');
            $table->enum('status', array_column(UserStatus::cases(), 'value'))
                ->default(UserStatus::Draft->value)
                ->comment('Status do usuário (ativo, inativo, etc.)');
            $table->string('phone')->nullable()->comment('Telefone para contato');
            $table->string('document')->nullable()->comment('Documento de identificação (CPF/CNPJ)');
            $table->string('avatar')->nullable()->comment('URL do avatar do usuário');
            $table->text('bio')->nullable()->comment('Biografia ou descrição do usuário');
            $table->json('settings')->nullable()->comment('Configurações personalizadas do usuário');
            $table->timestamp('last_login_at')->nullable()->comment('Data e hora do último login');
            $table->string('last_login_ip')->nullable()->comment('Endereço IP do último login');
            $table->timestamp('email_verified_at')->nullable()->comment('Data de verificação do email');
            $table->string('password');
            $table->text('two_factor_secret')->nullable()->comment('Secret key para TOTP (Time-based One-Time Password)');
            $table->text('two_factor_recovery_codes')->nullable()->comment('Códigos de recuperação do 2FA em formato JSON');
            $table->timestamp('two_factor_confirmed_at')->nullable()->comment('Data de confirmação/ativação do 2FA');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica');
        });

        // Tabela para reset de senhas
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Tabela para sessões
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('raptor.tables.users', 'users');

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists($tableName);
    }
};
