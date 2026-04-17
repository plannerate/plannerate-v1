<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Cria tabela de traduções (filha de translation_groups).
     */
    public function up(): void
    {
        $tableName = config('raptor.tables.translation_overrides', 'translation_overrides');
        $groupTable = config('raptor.tables.translation_groups', 'translation_groups');

        Schema::create($tableName, function (Blueprint $table) use ($groupTable) {
            $table->ulid('id')->primary();

            $table->foreignUlid('translation_group_id')
                ->constrained($groupTable)
                ->cascadeOnDelete()
                ->comment('Grupo pai desta tradução');

            $table->string('key')
                ->comment('Chave da tradução (ex: product, add_to_cart)');

            $table->text('value')
                ->comment('Valor traduzido customizado');

            $table->timestamps();

            // Constraint: não permitir chaves duplicadas no mesmo grupo
            $table->unique(['translation_group_id', 'key'], 'unique_key_per_group');

            // Index para buscas
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('raptor.tables.translation_overrides', 'translation_overrides'));
    }
};
