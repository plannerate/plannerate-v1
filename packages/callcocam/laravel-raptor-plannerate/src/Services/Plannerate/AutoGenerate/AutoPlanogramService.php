<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Concerns\BelongsToConnection;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateResultDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service Principal de Geração Automática de Planogramas
 *
 * Orquestra todo o processo:
 * 1. Seleção e ranqueamento de produtos (ProductSelectionService)
 * 2. Aplicação de regras de merchandising (MerchandisingRulesService)
 * 3. Otimização de layout (LayoutOptimizationService)
 * 4. Geração do resultado final (AutoGenerateResultDTO)
 */
class AutoPlanogramService
{
    use BelongsToConnection, UsesPlannerateTenantDatabase;

    public function __construct(
        protected ProductSelectionService $productSelection,
        protected MerchandisingRulesService $merchandisingRules,
        protected LayoutOptimizationService $layoutOptimization,
    ) {}

    /**
     * Gerar planograma automaticamente para uma gôndola específica
     *
     * @throws \Exception Se não encontrar gôndola ou planograma
     */
    public function generate(string $gondolaId, AutoGenerateConfigDTO $config): AutoGenerateResultDTO
    {
        Log::info('🚀 Iniciando geração automática de planograma', [
            'gondola_id' => $gondolaId,
            'config' => $config->toArray(),
        ]);

        // 0. Configurar conexão do tenant correto
        $tenant = Tenant::current();
        if ($tenant) {
            $this->setupTenantConnection($tenant);
            Log::info('✅ Conexão do tenant configurada', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'database' => $tenant->database,
                'connection' => $this->getTenantConnection(),
            ]);
        }

        // 1. Buscar gôndola específica
        $gondola = Gondola::with(['sections.shelves'])
            ->find($gondolaId);

        if (! $gondola) {
            throw new \Exception("Gôndola não encontrada: {$gondolaId}");
        }

        // 2. Buscar planograma correto (Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram, não Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram)
        $planogram = Planogram::with(['category'])
            ->find($gondola->planogram_id);

        if (! $planogram) {
            throw new \Exception("Planograma não encontrado: {$gondola->planogram_id}");
        }

        Log::info('✅ Gôndola e planograma encontrados', [
            'gondola_id' => $gondola->id,
            'planogram_id' => $planogram->id,
            'planogram_name' => $planogram->name,
            'category_id' => $planogram->category_id,
        ]);

        // 2. Selecionar e rankear produtos
        Log::info('🔍 Selecionando e ranqueando produtos...');
        $rankedProducts = $this->productSelection->selectAndRankProducts($planogram, $config);

        if ($rankedProducts->isEmpty()) {
            Log::warning('⚠️  Nenhum produto encontrado para a categoria', [
                'category_id' => $planogram->category_id,
            ]);

            return AutoGenerateResultDTO::empty($config);
        }

        Log::info('✅ Produtos selecionados e ranqueados', [
            'total_products' => $rankedProducts->count(),
            'top_5' => $rankedProducts->take(5)->map(fn ($p) => [
                'name' => $p->product->name,
                'abc' => $p->abcClass,
                'score' => round($p->score, 2),
            ]),
        ]);

        // 3. Distribuir produtos nas prateleiras
        Log::info('📊 Distribuindo produtos nas prateleiras...');
        $distribution = $this->layoutOptimization->distributeProducts($gondola, $rankedProducts, $config);

        $totalAllocated = collect($distribution['shelves'])->sum(fn ($shelf) => count($shelf->products));
        $totalUnallocated = count($distribution['unallocated']);

        Log::info('✅ Distribuição concluída', [
            'total_allocated' => $totalAllocated,
            'total_unallocated' => $totalUnallocated,
            'shelves_used' => count($distribution['shelves']),
        ]);

        // 4. Limpar gôndola e salvar novos produtos
        Log::info('🗑️  Limpando gôndola...');
        $this->clearGondola($gondola);

        Log::info('💾 Salvando produtos na gôndola...');
        $this->saveProductsToGondola($gondola, $distribution['shelves']);

        // 5. Criar resultado
        $result = new AutoGenerateResultDTO(
            shelves: $distribution['shelves'],
            unallocatedProducts: $distribution['unallocated'],
            totalAllocated: $totalAllocated,
            totalUnallocated: $totalUnallocated,
            config: $config,
            generatedAt: now()->toIso8601String(),
        );

        Log::info('🎉 Geração automática concluída com sucesso!');

        return $result;
    }

    /**
     * Limpar todos os segments e layers da gôndola
     */
    protected function clearGondola(Gondola $gondola): void
    {
        $this->plannerateTenantDatabase()->transaction(function () use ($gondola) {
            // Pegar IDs de todas as shelves da gôndola
            $shelfIds = [];
            foreach ($gondola->sections as $section) {
                foreach ($section->shelves as $shelf) {
                    $shelfIds[] = $shelf->id;
                }
            }

            if (empty($shelfIds)) {
                return;
            }

            // Deletar segments (cascade vai deletar layers)
            $deletedSegments = Segment::whereIn('shelf_id', $shelfIds)->delete();

            Log::info('🗑️  Gôndola limpa', [
                'gondola_id' => $gondola->id,
                'segments_deleted' => $deletedSegments,
            ]);
        });
    }

    /**
     * Salvar produtos distribuídos no banco de dados
     *
     * @param  ShelfLayoutDTO[]  $shelves
     */
    protected function saveProductsToGondola(Gondola $gondola, array $shelves): void
    {
        $this->plannerateTenantDatabase()->transaction(function () use ($gondola, $shelves) {
            $totalCreated = 0;

            foreach ($shelves as $shelfLayout) {
                // Buscar a shelf pelo ID
                $shelf = Shelf::find($shelfLayout->id);

                if (! $shelf) {
                    Log::warning('⚠️  Shelf não encontrada', [
                        'shelf_id' => $shelfLayout->id,
                        'shelf_index' => $shelfLayout->shelfIndex,
                    ]);

                    continue;
                }

                $ordering = 0;

                // Criar segment + layer para cada produto
                foreach ($shelfLayout->products as $rankedProduct) {
                    // Criar Segment
                    $segment = Segment::create([
                        'id' => (string) Str::ulid(),
                        'shelf_id' => $shelf->id,
                        'quantity' => 1, // Sem empilhamento (v1)
                        'ordering' => $ordering++,
                    ]);

                    // Criar Layer
                    Layer::create([
                        'id' => (string) Str::ulid(),
                        'segment_id' => $segment->id,
                        'product_id' => $rankedProduct->product->id,
                        'quantity' => $rankedProduct->facings,
                    ]);

                    $totalCreated++;
                }
            }

            Log::info('💾 Produtos salvos no banco', [
                'gondola_id' => $gondola->id,
                'total_segments_created' => $totalCreated,
            ]);
        });
    }
}
