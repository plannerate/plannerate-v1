<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\LayerRepository;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\SegmentRepository;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\ShelfRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Layers (Camadas de produtos)
 */
class LayerService
{
    public function __construct(
        private LayerRepository $repository,
        private SegmentRepository $segmentRepository,
        private ShelfRepository $shelfRepository,
        private SegmentService $segmentService
    ) {}

    /**
     * Cria ou atualiza uma layer baseado no tipo de mudança
     *
     * @param  array<string, mixed>  $change
     */
    public function createOrUpdate(array $change): bool
    {
        $type = $change['type'];

        return match ($type) {
            'layer_create' => $this->createSegmentAndLayer($change['data']),
            'layer_update' => $this->update($change['entityId'], $change['data']),
            'product_removal' => $this->remove($change['entityId']),
            default => false
        };
    }

    /**
     * Cria segment + layer (adiciona produto à prateleira)
     *
     * Layer create é composto: cria segment E layer juntos
     * Frontend envia: { segment: {...}, layer: {...} }
     *
     * @param  array<string, mixed>  $data
     */
    public function createSegmentAndLayer(array $data): bool
    {
        // Validação: deve ter segment e layer
        if (! isset($data['segment'], $data['layer'])) {
            Log::warning('⚠️ layer_create sem segment ou layer', ['data' => $data]);

            return false;
        }

        $segment = $data['segment'];
        $layer = $data['layer'];

        // Validações adicionais
        if (! isset($segment['id'], $segment['shelf_id'], $layer['id'], $layer['product_id'])) {
            Log::warning('⚠️ layer_create com dados incompletos', ['data' => $data]);

            return false;
        }

        // Busca shelf para obter tenant_id
        $shelf = $this->shelfRepository->find($segment['shelf_id']);
        if (! $shelf) {
            Log::warning('⚠️ Shelf não encontrada', ['shelf_id' => $segment['shelf_id']]);

            return false;
        }

        // Filtra campos válidos do segment
        $segmentFields = ['id', 'shelf_id', 'width', 'height', 'depth', 'position_x', 'position_y', 'ordering', 'alignment', 'spacing', 'quantity', 'deleted_at'];
        $segmentData = array_intersect_key($segment, array_flip($segmentFields));

        // Cria segment
        $this->segmentService->create($segmentData, $shelf->tenant_id ?? null);

        Log::info('✅ Segment criado', [
            'segment_id' => $segment['id'],
            'shelf_id' => $segment['shelf_id'],
        ]);

        // Filtra campos válidos da layer
        $layerFields = ['id', 'segment_id', 'product_id', 'height', 'alignment', 'spacing', 'quantity', 'deleted_at'];
        $layerData = array_intersect_key($layer, array_flip($layerFields));

        // Cria layer
        $this->createForSegment($segment['id'], $layerData, $shelf->tenant_id ?? null);

        Log::info('✅ Layer criada', [
            'layer_id' => $layer['id'],
            'segment_id' => $segment['id'],
            'product_id' => $layer['product_id'],
        ]);

        return true;
    }

    /**
     * Cria uma layer para um segment
     *
     * @param  array<string, mixed>  $data
     */
    public function createForSegment(string $segmentId, array $data, ?string $tenantId): bool
    {
        return $this->repository->create(array_merge($data, [
            'segment_id' => $segmentId,
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * Atualiza propriedades da layer
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $layerId, array $data): bool
    {
        $allowedFields = ['position_x', 'position_y', 'position_z', 'quantity', 'rotation', 'deleted_at'];
        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updates)) {
            return false;
        }

        // Normaliza deleted_at
        if (isset($updates['deleted_at']) && is_string($updates['deleted_at'])) {
            $updates['deleted_at'] = Carbon::parse($updates['deleted_at'])->format('Y-m-d H:i:s');
        }

        $updates['updated_at'] = now();

        $updated = $this->repository->update($layerId, $updates);

        return $updated > 0;
    }

    /**
     * Remove layer (e segment se for a última layer do segment)
     */
    public function remove(string $productId): bool
    {
        // Busca layer pelo product_id
        $layer = $this->repository->findByProductId($productId);
        if (! $layer) {
            Log::warning('⚠️ Layer para remoção não encontrada', ['product_id' => $productId]);

            return false;
        }

        // Busca segment
        $segment = $this->segmentRepository->find($layer->segment_id);
        if (! $segment) {
            Log::warning('⚠️ Segmento para remoção não encontrado', ['segment_id' => $layer->segment_id]);

            return false;
        }

        // Se for a última layer do segment, remove segment também
        if ($this->repository->countBySegmentId($segment->id) <= 1) {
            $this->segmentRepository->delete($segment->id);
            Log::info('🗑️ Segment removido (última layer)', ['segment_id' => $segment->id]);
        }

        // Remove layer
        $updated = $this->repository->delete($layer->id);

        if ($updated > 0) {
            Log::info('🗑️ Layer removida', ['layer_id' => $layer->id]);
        }

        return $updated > 0;
    }
}
