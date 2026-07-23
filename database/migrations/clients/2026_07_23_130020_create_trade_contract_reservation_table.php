<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ações (reservas) cobertas pelo contrato. Uma ação só pode estar num
     * contrato — a unique em `reservation_id` é o que garante isso no banco,
     * e não só na validação.
     */
    public function up(): void
    {
        Schema::create('trade_contract_reservation', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();
            $table->foreignUlid('contract_id')->index();
            $table->foreignUlid('reservation_id')->index();
            $table->timestamps();

            $table->unique('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_contract_reservation');
    }
};
