<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uma mesma ação pode rodar em várias lojas: o vínculo é com a planta
     * (mapa) de cada loja. `trade_reservations.map_id` guarda a primeira delas,
     * para leitura direta; esta tabela é a fonte da verdade do conjunto.
     */
    public function up(): void
    {
        Schema::create('trade_reservation_store_map', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('reservation_id')->index();
            $table->foreignUlid('map_id')->index();
            $table->timestamps();

            $table->unique(['reservation_id', 'map_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_reservation_store_map');
    }
};
