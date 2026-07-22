<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move para o vínculo produto↔loja as métricas que são POR UNIDADE.
 *
 * `products.current_stock` e `products.last_purchase_date` vinham do feed do ERP,
 * onde ambas são da unidade consultada: estoque é da loja, e a última compra é a
 * daquela filial. Com um tenant de uma loja só isso passava despercebido; com
 * duas, as cadeias de importação gravam na MESMA linha de `products` (o id do
 * produto deriva de tenant+ean, sem loja) e o valor final é o da última cadeia a
 * terminar — não-determinístico e sem significado.
 *
 * Medido na RP Info (Supermercado Maringá): 67 de 100 produtos da primeira página
 * têm estoque diferente entre a Matriz e a Filial.
 *
 * As colunas em `products` continuam existindo nesta migration — a troca dos
 * leitores é feita à parte (ver `.claude/plano-current-stock-por-loja.md`).
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('product_store', function (Blueprint $table): void {
            $table->double('current_stock')->nullable()->after('store_id');
            $table->date('last_purchase_date')->nullable()->after('current_stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_store', function (Blueprint $table): void {
            $table->dropColumn(['current_stock', 'last_purchase_date']);
        });
    }
};
