<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tabela CENTRAL — compartilhada entre todos os tenants (mesmo EAN = mesmas dimensões)
    public function up(): void
    {
        Schema::create('dimension_research_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('ean', 100)->unique();
            $table->json('dimensions')->nullable();
            $table->string('source', 50)->nullable()->comment('cosmos|web_search');
            $table->string('confidence', 20)->nullable()->comment('high|medium|low');
            $table->text('raw_response')->nullable();
            $table->timestamp('cached_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dimension_research_cache');
    }
};
