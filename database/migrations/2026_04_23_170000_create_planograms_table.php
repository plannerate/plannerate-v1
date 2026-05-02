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
        Schema::create('planograms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->string('template_id')->nullable();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->foreignUlid('store_id')->nullable();
            $table->foreignUlid('cluster_id')->nullable();
            $table->string('name')->nullable()->comment('Nome do planograma');
            $table->string('slug')->comment('Slug do planograma');
            $table->enum('type', ['realograma', 'planograma'])->default('planograma')->comment('Tipo do planograma (Ex: Realograma ou planograma)');
            $table->foreignUlid('category_id')->nullable();
            $table->date('start_date')->nullable()->comment('Data de início do planograma');
            $table->date('end_date')->nullable()->comment('Data de término do planograma');
            $table->integer('order')->default(0);
            $table->string('qr_code_token', 64)->nullable()->unique();
            $table->timestamp('qr_code_generated_at')->nullable();
            $table->string('description')->nullable()->comment('Descrição do planograma');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'name']);
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
