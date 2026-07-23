<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_space_images', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('space_id')->index();
            $table->ulid('activity_id')->nullable()->comment('Preenchido a partir da Fase 5 (atividades)');
            $table->string('path');
            $table->foreignUlid('created_by')->nullable();
            $table->string('origin', 20)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['space_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_space_images');
    }
};
