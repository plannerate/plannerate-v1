<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intenção de compra: o fornecedor demonstra interesse num espaço —
     * disponível ou ocupado (neste caso, para quando liberar). O gestor do
     * trade analisa e pode aprovar, recusar ou contrapropor; ao aprovar, a
     * intenção vira uma reserva (negociação).
     */
    public function up(): void
    {
        Schema::create('trade_purchase_intentions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();

            $table->foreignUlid('space_id')->index();
            $table->foreignUlid('map_id')->nullable()->index();
            $table->foreignUlid('store_id')->nullable()->index();

            $table->foreignUlid('supplier_id')->index();
            $table->foreignUlid('supplier_user_id')->nullable()->index();

            $table->date('desired_start_date')->nullable();
            $table->date('desired_end_date')->nullable();
            $table->string('campaign')->nullable();
            $table->decimal('proposed_price', 12, 2)->nullable();
            $table->string('proposed_price_period', 20)->nullable();
            $table->string('timing', 20)->default('when_available');
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('sent');
            $table->text('manager_response')->nullable();
            $table->decimal('counter_price', 12, 2)->nullable();
            $table->foreignUlid('responded_by')->nullable()->index();
            $table->timestamp('responded_at')->nullable();

            $table->foreignUlid('reservation_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_purchase_intentions');
    }
};
