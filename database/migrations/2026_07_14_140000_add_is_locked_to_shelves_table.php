<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prateleira travada: a geração não mexe nela.
 *
 * A geração de planograma é destrutiva — apaga todos os segments da gôndola e recria. Quem
 * montou uma prateleira à mão (trocou um produto rejeitado, ajustou frentes) perdia tudo na
 * regeração seguinte, sem aviso e sem recuperação. Travar é a forma de dizer "esta eu decidi,
 * não recalcule".
 *
 * O lock é por PRATELEIRA, não por segmento: o motor empacota cada prateleira contiguamente da
 * esquerda para a direita, então travar um segmento no meio exigiria empacotar em volta de um
 * obstáculo fixo — e um erro ali produz sobreposição física. Prateleira inteira é a unidade que
 * o motor já sabe pular sem risco.
 *
 * Rodar via: docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('shelves', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->index()
                ->comment('true = a geração preserva esta prateleira e seus produtos saem do pool');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('shelves', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
