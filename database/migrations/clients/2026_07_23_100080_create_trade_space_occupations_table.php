<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_space_occupations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('space_id')->index();
            $table->ulid('reservation_id')->nullable()->comment('Preenchido a partir da Fase 3 (reservas)');
            $table->ulid('supplier_id')->nullable();
            $table->string('tipo', 30)->default('reserva');
            $table->string('ocupante')->nullable();
            $table->string('status', 30)->default('ativo');
            $table->text('motivo')->nullable();
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->foreignUlid('closed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('reservation_id');
            $table->index(['space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_space_occupations');
    }
};
