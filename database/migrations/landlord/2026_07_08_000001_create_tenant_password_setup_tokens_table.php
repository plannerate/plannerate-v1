<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->create('tenant_password_setup_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->ulid('target_user_id');
            $table->string('target_user_name')->nullable();
            $table->string('target_user_email')->nullable();

            $table->ulid('issuer_id')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_email')->nullable();

            $table->string('code_hash', 64)->unique();
            $table->string('status')->default('pending');
            $table->string('used_reason')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['target_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_password_setup_tokens');
    }
};
