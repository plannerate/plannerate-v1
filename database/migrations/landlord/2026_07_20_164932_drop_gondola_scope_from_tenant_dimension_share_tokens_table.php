<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove o escopo por gôndola do link de correção de dimensões.
 *
 * O recorte é sempre vazio na prática: produto sem dimensão nunca chega a ser
 * posicionado numa gôndola. O escopo útil é a categoria do planograma, que já
 * existe via category_id.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->table('tenant_dimension_share_tokens', function (Blueprint $table): void {
            $table->dropColumn(['gondola_id', 'gondola_name']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('tenant_dimension_share_tokens', function (Blueprint $table): void {
            $table->ulid('gondola_id')->nullable()->after('category_name');
            $table->string('gondola_name')->nullable()->after('gondola_id');
        });
    }
};
