<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela satélite 1:1 com `stores` (host) para os campos trade-específicos
     * da loja, que não existem no model do host (D6 do PLANO). O cluster é
     * textual aqui — não se usa o `Cluster` do host (semântica invertida).
     */
    public function up(): void
    {
        Schema::create('trade_store_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('store_id')->unique();
            $table->string('tipo')->nullable();
            $table->integer('number_of_deposits')->nullable();
            $table->integer('promotional_areas')->nullable();
            $table->string('cluster')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_store_profiles');
    }
};
