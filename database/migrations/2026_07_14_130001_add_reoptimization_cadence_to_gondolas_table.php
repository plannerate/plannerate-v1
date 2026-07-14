<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadência de reotimização por gôndola.
 *
 * A frequência é por gôndola (e não global) porque o ritmo de mudança depende da categoria:
 * hortifruti muda toda semana, mercearia seca não muda em um mês. Um agendamento único forçaria
 * o pior caso para todo mundo — ou ruído semanal em categorias estáveis, ou defasagem nas voláteis.
 *
 * `next_run_at` é avançado no ENFILEIRAMENTO (não no fim do job): se o job falhar, a gôndola não
 * fica presa tentando de novo a cada rodada do agendador.
 *
 * Rodar via: docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('gondolas', function (Blueprint $table) {
            $table->boolean('reoptimization_enabled')->default(false)->index();
            $table->string('reoptimization_frequency', 20)->nullable()
                ->comment('weekly | biweekly | monthly');
            $table->timestamp('reoptimization_last_run_at')->nullable();
            $table->timestamp('reoptimization_next_run_at')->nullable()->index()
                ->comment('Quando o agendador deve reprocessar; avançado no enfileiramento');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('gondolas', function (Blueprint $table) {
            $table->dropColumn([
                'reoptimization_enabled',
                'reoptimization_frequency',
                'reoptimization_last_run_at',
                'reoptimization_next_run_at',
            ]);
        });
    }
};
