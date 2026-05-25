<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        $isPgsql = DB::connection($this->connection)->getDriverName() === 'pgsql';

        // Renomeia coluna legacy (enum draft/published) → dimension_publish_status
        Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
            $table->renameColumn('dimension_status', 'dimension_publish_status');
        });

        // Adiciona os novos campos do pipeline AI (funciona em qualquer driver)
        Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
            $table->string('dimension_status')->default('pending')
                ->comment('Pipeline AI: pending|researching|awaiting_approval|approved|not_found|rejected')
                ->after('has_dimensions');

            $table->string('similar_to_product_id')->nullable()
                ->comment('Produto referência usado na similaridade local')
                ->after('dimension_status');

            $table->string('dimension_source')->nullable()
                ->comment('local_similarity|cosmos|web_search')
                ->after('similar_to_product_id');

            $table->string('dimension_source_url')->nullable()
                ->after('dimension_source');

            $table->string('dimension_confidence')->nullable()
                ->comment('high|medium|low')
                ->after('dimension_source_url');

            $table->text('dimension_reasoning')->nullable()
                ->after('dimension_confidence');

            $table->json('dimension_warnings')->nullable()
                ->after('dimension_reasoning');

            $table->timestamp('dimension_researched_at')->nullable()
                ->after('dimension_warnings');

            $table->string('dimension_approved_by')->nullable()
                ->after('dimension_researched_at');

            $table->timestamp('dimension_approved_at')->nullable()
                ->after('dimension_approved_by');

            $table->decimal('net_content', 10, 3)->nullable()
                ->comment('Conteúdo líquido numérico para filtro ±5%')
                ->after('dimension_approved_at');
        });

        if (! $isPgsql) {
            return;
        }

        DB::connection($this->connection)->statement(
            "UPDATE products SET dimension_status = 'approved'
             WHERE width IS NOT NULL AND height IS NOT NULL AND depth IS NOT NULL
               AND width > 0 AND height > 0 AND depth > 0"
        );

        Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
            $table->foreign('similar_to_product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });

        // Coluna vector(768) e índice HNSW — requerem a extensão pgvector instalada
        $hasVector = DB::connection($this->connection)
            ->selectOne("SELECT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'vector') AS has_vector")
            ->has_vector;

        if (! $hasVector) {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE products ADD COLUMN IF NOT EXISTS description_embedding vector(768)'
        );

        DB::connection($this->connection)->statement(
            'CREATE INDEX IF NOT EXISTS products_description_embedding_idx
             ON products USING hnsw (description_embedding vector_cosine_ops)'
        );
    }

    public function down(): void
    {
        $isPgsql = DB::connection($this->connection)->getDriverName() === 'pgsql';

        if ($isPgsql) {
            Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
                $table->dropForeign(['similar_to_product_id']);
            });

            $hasVector = DB::connection($this->connection)
                ->selectOne("SELECT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'vector') AS has_vector")
                ->has_vector;

            if ($hasVector) {
                DB::connection($this->connection)->statement(
                    'DROP INDEX IF EXISTS products_description_embedding_idx'
                );

                DB::connection($this->connection)->statement(
                    'ALTER TABLE products DROP COLUMN IF EXISTS description_embedding'
                );
            }
        }

        Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'dimension_status', 'similar_to_product_id', 'dimension_source',
                'dimension_source_url', 'dimension_confidence', 'dimension_reasoning',
                'dimension_warnings', 'dimension_researched_at', 'dimension_approved_by',
                'dimension_approved_at', 'net_content',
            ]);
        });

        Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
            $table->renameColumn('dimension_publish_status', 'dimension_status');
        });
    }
};
