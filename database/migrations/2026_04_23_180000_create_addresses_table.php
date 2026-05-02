<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('addresses')) {
            Schema::connection($this->connection)->create('addresses', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('type')->nullable();
                $table->ulid('tenant_id')->nullable()->index();
                $table->ulid('user_id')->nullable()->index();
                $table->ulidMorphs('addressable');
                $table->string('name')->nullable();
                $table->string('zip_code', 15)->nullable();
                $table->string('street')->nullable();
                $table->string('number')->nullable();
                $table->string('complement')->nullable();
                $table->string('reference')->nullable();
                $table->string('additional_information')->nullable();
                $table->string('district')->nullable();
                $table->string('city')->nullable();
                $table->string('country', 100)->default('Brasil');
                $table->string('state', 2)->nullable();
                $table->boolean('is_default')->default(false);
                $table->enum('status', ['draft', 'published'])->default('draft');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'is_default']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('addresses');
    }
};
