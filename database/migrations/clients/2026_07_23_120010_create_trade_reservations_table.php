<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reserva (= "ação" comercial): ocupa um espaço promocional num período,
     * para um cliente/fornecedor, com preço e fluxo de aprovação.
     *
     * O preço é `unit_price` + `price_period` (a origem chamava de weekly_price
     * mesmo quando o período era diário/mensal). Os valores derivados
     * (total/final) são calculados no backend pelo ReservationPricing.
     */
    public function up(): void
    {
        Schema::create('trade_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('name')->nullable();

            $table->foreignUlid('space_id')->index();
            $table->foreignUlid('map_id')->nullable()->index();
            $table->foreignUlid('supplier_id')->nullable()->index();
            $table->foreignUlid('supplier_user_id')->nullable()->index();

            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone', 50)->nullable();
            $table->string('client_company')->nullable();
            $table->text('client_notes')->nullable();

            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('duration_weeks', 8, 2)->default(0);

            $table->string('status', 20)->default('pending');

            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('price_period', 20)->default('week');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('expense_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);

            $table->string('payment_method', 20)->nullable();
            $table->string('payment_status', 20)->default('pending');

            $table->foreignUlid('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->date('installation_date')->nullable();
            $table->date('removal_date')->nullable();
            $table->text('installation_notes')->nullable();

            $table->json('attachments')->nullable();
            $table->text('contract_notes')->nullable();
            $table->text('change_log')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'payment_status']);
            $table->index(['space_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_reservations');
    }
};
