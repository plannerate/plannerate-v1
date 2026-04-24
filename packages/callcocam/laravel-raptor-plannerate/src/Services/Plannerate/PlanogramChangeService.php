<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\GondolaRepository;
use Illuminate\Support\Facades\Log;

/**
 * Service orquestrador para processar mudanças no planograma
 *
 * Responsável por rotear mudanças para os services específicos de cada entidade
 */
class PlanogramChangeService
{
    public function __construct(
        private ShelfService $shelfService,
        private SectionService $sectionService,
        private SegmentService $segmentService,
        private LayerService $layerService,
        private ProductService $productService,
        private GondolaService $gondolaService,
        private GondolaRepository $gondolaRepository
    ) {}

    /**
     * Processa uma mudança e roteia para o service apropriado
     *
     * @param  array<string, mixed>  $change
     */
    public function applyChange(array $change): bool
    {
        try {
            // Roteamento por tipo de entidade
            return match ($change['entityType']) {
                'shelf' => $this->shelfService->createOrUpdate($change),
                'section' => $this->sectionService->createOrUpdate($change),
                'segment' => $this->segmentService->createOrUpdate($change),
                'layer' => $this->layerService->createOrUpdate($change),
                'product' => $this->productService->createOrUpdate($change),
                'gondola' => $this->gondolaService->createOrUpdate($change),
                default => throw new \Exception("Entity type não suportado: {$change['entityType']}")
            };
        } catch (\Exception $e) {
            Log::error('❌ Erro ao aplicar mudança', [
                'change' => $change,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Processa múltiplas mudanças e atualiza a gôndola
     *
     * @param  array<int, array<string, mixed>>  $changes
     */
    public function processChanges(string $gondolaId, array $changes): int
    {
        $changesApplied = 0;

        // Processa cada mudança
        foreach ($changes as $change) {
            $applied = $this->applyChange($change);
            if ($applied) {
                $changesApplied++;
            }
        }

        // Atualiza timestamp da gôndola (marca como modificada)
        $gondola = $this->gondolaRepository->findOrFail($gondolaId);
        $gondola->touch();

        return $changesApplied;
    }
}
