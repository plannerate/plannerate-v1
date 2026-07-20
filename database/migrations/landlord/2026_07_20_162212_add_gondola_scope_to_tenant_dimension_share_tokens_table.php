<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->table('tenant_dimension_share_tokens', function (Blueprint $table): void {
            // Escopo alternativo ao de categoria: os produtos posicionados numa gôndola.
            // Sem FK — a gôndola vive no banco do tenant, mesma razão de category_id.
            // Escopo declarado, não congelado: a lista de produtos é derivada na leitura,
            // então acompanha as mudanças feitas no planograma depois da emissão.
            $table->ulid('gondola_id')->nullable()->after('category_name');
            $table->string('gondola_name')->nullable()->after('gondola_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('tenant_dimension_share_tokens', function (Blueprint $table): void {
            $table->dropColumn(['gondola_id', 'gondola_name']);
        });
    }
};
