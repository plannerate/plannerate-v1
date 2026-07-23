<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acordo comercial com o fornecedor. Consolida numa migration só o que na
     * origem foram dez migrations incrementais (identificação, vigência,
     * partes, condições financeiras, abrangência, condições operacionais e
     * fluxo de aprovação).
     *
     * O contrato pode nascer de ações já reservadas (`from_actions`, valor
     * somado do pivô) ou ser um retorno fixo mensal (`fixed_monthly`), caso em
     * que o valor vem de `fixed_monthly_amount` e a abrangência por loja passa
     * a valer.
     */
    public function up(): void
    {
        Schema::create('trade_contracts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();
            TradeSchema::reference($table, 'user_id', 'user')->nullable()->index();

            $table->string('contract_number', 30);

            // Partes
            TradeSchema::reference($table, 'supplier_id', 'provider')->nullable()->index();
            TradeSchema::reference($table, 'supplier_user_id', 'user')->nullable()->index();
            $table->string('contractor_company_name')->nullable();
            $table->string('contractor_cnpj', 20)->nullable();
            $table->string('contractor_contact_name')->nullable();
            $table->string('contractor_contact_role', 100)->nullable();
            $table->string('contractor_contact_email')->nullable();

            // Identificação e vigência
            $table->string('agreement_type', 30)->nullable();
            $table->string('scope', 30)->nullable();
            $table->json('scope_store_ids')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('auto_renewal')->default(false);
            $table->unsignedInteger('cancellation_notice_days')->nullable();

            // Condições financeiras
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->decimal('fixed_monthly_amount', 12, 2)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->unsignedTinyInteger('payment_day_of_month')->nullable();
            $table->string('billing_type', 30)->nullable();
            $table->string('cost_center', 100)->nullable();
            $table->string('accounting_account', 100)->nullable();
            $table->string('adjustment_index', 20)->nullable();
            $table->string('adjustment_frequency', 20)->nullable();

            // Abrangência
            $table->string('scope_apply', 30)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('subcategory', 100)->nullable();
            $table->text('linked_products')->nullable();
            $table->string('linked_brands')->nullable();

            // Condições operacionais
            $table->text('supplier_obligations')->nullable();
            $table->text('contractor_obligations')->nullable();
            $table->text('maintenance_conditions')->nullable();
            $table->boolean('min_mix_required')->nullable();
            $table->string('min_exposure_notes')->nullable();
            $table->boolean('tabloid_participation')->nullable();
            $table->string('min_purchase_volume_notes')->nullable();

            // Ciclo de vida e negociação
            $table->string('status', 20)->default('draft');
            $table->string('supplier_approval_status', 20)->default('pending');
            $table->timestamp('supplier_approval_date')->nullable();
            $table->text('supplier_rejection_reason')->nullable();
            $table->unsignedInteger('negotiation_round')->default(1);
            $table->json('negotiation_history')->nullable();
            $table->timestamp('sent_to_supplier_at')->nullable();
            $table->timestamp('last_modified_by_admin')->nullable();
            $table->boolean('is_resubmitted')->default(false);

            $table->text('notes')->nullable();
            $table->text('change_log')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // O número é único por cliente, e não global: no driver team todos
            // os clientes dividem a mesma tabela e a sequência é por escopo.
            $table->unique([TradeSchema::ownerColumn(), 'contract_number']);
            $table->index([TradeSchema::ownerColumn(), 'status']);
            $table->index([TradeSchema::ownerColumn(), 'supplier_approval_status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_contracts');
    }
};
