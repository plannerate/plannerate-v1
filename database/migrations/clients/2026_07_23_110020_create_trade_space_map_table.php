<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posicionamento de um espaço numa planta de loja. Posições/dimensões são
     * percentuais (0–100) da imagem de fundo — independentes da resolução de
     * exibição. Cada colocação (placement) é uma linha de primeira classe.
     */
    public function up(): void
    {
        Schema::create('trade_space_map', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('map_id')->index();
            $table->foreignUlid('space_id')->index();
            $table->decimal('position_x', 6, 2)->default(45);
            $table->decimal('position_y', 6, 2)->default(45);
            $table->decimal('width', 6, 2)->default(10);
            $table->decimal('height', 6, 2)->default(8);
            $table->smallInteger('rotation')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['map_id', 'space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_space_map');
    }
};
