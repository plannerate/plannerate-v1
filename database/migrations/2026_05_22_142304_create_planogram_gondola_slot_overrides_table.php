<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->create('planogram_gondola_slot_overrides', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->char('gondola_id', 26)->index();
            $table->char('category_id', 26)->nullable()->index();

            // Overrides de configuração — todos nullable (null = usar default do template slot)
            $table->unsignedTinyInteger('min_facings')->nullable();
            $table->unsignedTinyInteger('max_facings')->nullable();
            $table->string('price_order')->nullable();
            $table->string('size_order')->nullable();
            $table->string('brand_exposure')->nullable();
            $table->string('flavor_exposure')->nullable();
            $table->string('space_fallback')->nullable();
            $table->string('facing_expansion')->nullable();
            $table->boolean('use_target_stock')->nullable();
            $table->string('role_override')->nullable();
            $table->unsignedSmallInteger('max_share_per_sku')->nullable();
            $table->unsignedSmallInteger('max_share_per_brand')->nullable();
            $table->unsignedSmallInteger('max_share_per_subcategory')->nullable();

            $table->unique(['gondola_id', 'category_id']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('planogram_gondola_slot_overrides');
    }
};
