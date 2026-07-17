<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->create('tenant_dimension_share_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Escopo: categoria do banco do tenant (sem FK — vive em outra conexão).
            $table->ulid('category_id')->nullable();
            $table->string('category_name')->nullable();

            // O emissor é um usuário do tenant (conexão tenant), não do landlord —
            // por isso sem FK para a tabela users (que fica no landlord).
            $table->ulid('issuer_id')->nullable()->index();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_email')->nullable();

            $table->string('label')->nullable();
            $table->string('code_hash', 64)->unique();
            $table->string('status')->default('active');

            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_dimension_share_tokens');
    }
};
