<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue execuções que ESCREVEM na gôndola das que apenas SIMULAM.
 *
 * A reotimização contínua roda o pipeline em dry-run para montar a proposta que o usuário
 * revisa. Essa execução merece o mesmo registro das outras (duração, ocupação, validação),
 * mas não é "a última geração da gôndola": sem `kind`, o editor exibiria uma simulação de
 * background como se fosse a geração corrente — e recarregaria a tela sozinho ao vê-la
 * concluir.
 *
 * `trigger` separa o que o usuário pediu do que o agendador disparou.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('planogram_generation_runs', function (Blueprint $table) {
            $table->string('kind', 20)->default('apply')->index()
                ->comment('apply = escreve na gôndola | proposal = simulação (dry-run) da reotimização');

            $table->string('trigger', 20)->default('manual')
                ->comment('manual = solicitado pelo usuário | scheduled = disparado pelo agendador');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('planogram_generation_runs', function (Blueprint $table) {
            $table->dropColumn(['kind', 'trigger']);
        });
    }
};
