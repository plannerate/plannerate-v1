<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('landlord')->create('plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('user_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('landlord')->create('modules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('landlord')->create('tenants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database')->unique();
            $table->string('status')->default('provisioning');
            $table->foreignUlid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('user_limit')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->text('provisioning_error')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::connection('landlord')->create('tenant_domains', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('host')->unique();
            $table->string('type')->default('subdomain');
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::connection('landlord')->create('tenant_modules', function (Blueprint $table): void {
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_id']);
        });

        Schema::connection('landlord')->create('tenant_external_api_configs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider');
            $table->string('base_url')->nullable();
            $table->json('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('tenant_external_api_configs');
        Schema::connection('landlord')->dropIfExists('tenant_modules');
        Schema::connection('landlord')->dropIfExists('tenant_domains');
        Schema::connection('landlord')->dropIfExists('tenants');
        Schema::connection('landlord')->dropIfExists('modules');
        Schema::connection('landlord')->dropIfExists('plans');
    }
};
