<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de atividade (execução, verificação, auditoria, montagem…).
     *
     * A atividade referencia o tipo pelo `slug` (não pela PK), então o slug é
     * único por dono. `is_audit` + `audit_config` marcam tipos que exigem
     * checklist/fotos/motivo de não conformidade na conclusão.
     */
    public function up(): void
    {
        Schema::create('trade_activity_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_audit')->default(false);
            $table->json('audit_config')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([TradeSchema::ownerColumn(), 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_activity_types');
    }
};
