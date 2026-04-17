<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connection(): string
    {
        return (string) config('raptor.database.landlord_connection_name', 'landlord');
    }

    public function up(): void
    {
        Schema::connection($this->connection())->create(config('login_as.table', 'login_as_tokens'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('token_hash', 64)->unique();
            $table->foreignUlid('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists(config('login_as.table', 'login_as_tokens'));
    }
};
