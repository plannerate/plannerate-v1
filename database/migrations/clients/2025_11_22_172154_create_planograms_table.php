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
        Schema::create('planograms', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->string('template_id')->nullable();
            $table->char('user_id', 26)->nullable()->index('planograms_user_id_index');
            $table->char('client_id', 26)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->char('cluster_id', 26)->nullable();
            $table->string('name')->nullable()->comment("Nome do planograma");
            $table->string('slug')->unique('planograms_slug_unique')->comment("Slug do planograma");
            $table->enum('type', ['realograma', 'planograma'])->default('Planograma')->comment("Tipo do planograma (Ex: Realograma ou planograma)");
            $table->char('category_id', 26)->nullable();
            $table->date('start_date')->nullable()->comment("Data de início do planograma");
            $table->date('end_date')->nullable()->comment("Data de término do planograma");
            $table->integer('order')->default(0);
            $table->string('qr_code_token', 64)->nullable()->unique('planograms_qr_code_token_unique');
            $table->timestamp('qr_code_generated_at')->nullable();
            $table->string('description')->nullable()->comment("Descrição do planograma");
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planograms');
    }
};
