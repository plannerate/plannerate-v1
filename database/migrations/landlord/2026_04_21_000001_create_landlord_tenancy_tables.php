<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('user_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($this->connection)->create('modules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();   
            $table->softDeletes();
        });

        Schema::connection($this->connection)->create('tenants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable(); 
            $table->string('logo')->nullable();
            $table->string('database')->unique();
            $table->string('status')->default('provisioning');
            $table->foreignUlid('plan_id')->nullable()->constrained('plans')->nullOnDelete(); 
            $table->timestamp('provisioned_at')->nullable();
            $table->text('provisioning_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::connection($this->connection)->create('tenant_domains', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('host')->unique();
            $table->string('type')->default('subdomain');
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::connection($this->connection)->create('tenant_modules', function (Blueprint $table): void {
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamps(); 
            $table->unique(['tenant_id', 'module_id']);
        });

        Schema::connection($this->connection)->create('tenant_external_api_configs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider');
            $table->string('base_url')->nullable();
            $table->json('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_external_api_configs');
        Schema::connection($this->connection)->dropIfExists('tenant_modules');
        Schema::connection($this->connection)->dropIfExists('tenant_domains');
        Schema::connection($this->connection)->dropIfExists('tenants');
        Schema::connection($this->connection)->dropIfExists('modules');
        Schema::connection($this->connection)->dropIfExists('plans');
    }
};
