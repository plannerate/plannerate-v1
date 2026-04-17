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
        Schema::create('clusters', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_id', 26)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable()->index('clusters_user_id_index');
            $table->string('name');
            $table->string('epcification_1')->nullable();
            $table->string('epcification_2')->nullable();
            $table->string('epcification_3')->nullable();
            $table->string('slug')->nullable()->unique('clusters_slug_unique');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clusters');
    }
};
