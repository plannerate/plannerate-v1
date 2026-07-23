<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentação anexa do contrato: contrato assinado, proposta comercial,
     * e-mails de negociação, planilhas. `version` é a versão do documento
     * informada por quem sobe — o mesmo tipo pode ser anexado várias vezes.
     */
    public function up(): void
    {
        Schema::create('trade_contract_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();
            $table->foreignUlid('contract_id')->index();
            TradeSchema::reference($table, 'user_id', 'user')->nullable()->index();

            $table->string('document_type', 30);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedTinyInteger('version')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_contract_attachments');
    }
};
