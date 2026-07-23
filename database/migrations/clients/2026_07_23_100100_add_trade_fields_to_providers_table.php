<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos do "Parceiro" da origem que faltam no `Provider` do host (D5 do
     * PLANO). Todos nullable — o endereço continua via `Address` polimórfico.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            if (! Schema::hasColumn('providers', 'razao_social')) {
                $table->string('razao_social')->nullable()->after('name');
            }

            if (! Schema::hasColumn('providers', 'slug')) {
                $table->string('slug')->nullable()->after('razao_social');
            }

            if (! Schema::hasColumn('providers', 'status')) {
                $table->string('status', 30)->nullable()->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            foreach (['razao_social', 'slug', 'status'] as $column) {
                if (Schema::hasColumn('providers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
