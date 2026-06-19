<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('tenants', function (Blueprint $table): void {
            // Configurações por tenant em JSON. Hoje guarda apenas o padrão de
            // gôndola (settings.gondola) usado para pré-preencher a criação de
            // gôndolas, mas o formato permite outras chaves no futuro.
            $table->json('settings')->nullable()->after('provisioning_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('tenants', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};
