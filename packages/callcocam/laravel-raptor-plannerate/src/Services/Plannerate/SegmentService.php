<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\LayerRepository;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\SegmentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Segments (Segmentos)
 */
class SegmentService
{
    public function __construct(
        private SegmentRepository $repository,
        private LayerRepository $layerRepository
    ) {}

    /**
     * Cria ou atualiza um segment baseado no tipo de mudança
     *
     * @param  array<string, mixed>  $change
     */
    public function createOrUpdate(array $change): bool
    {
        $type = $change['type'];

        return match ($type) {
            'segment_copy' => $this->copySegment($change['data']),
            'segment_transfer' => $this->transferSegment($change['entityId'], $change['data']),
            'segment_reorder' => $this->reorderSegment($change['entityId'], $change['data']),
            'segment_update' => $this->update($change['entityId'], $change['data']),
            default => false
        };
    }

    /**
     * Copia um segment com sua layer (drag & drop com Ctrl)
     *
     * Frontend envia: { source_segment_id, shelf_id, position, layer: {...} }
     *
     * @param  array<string, mixed>  $data
     */
    public function copySegment(array $data): bool
    {
        // Validação: deve ter source_segment_id, shelf_id e layer
        if (! isset($data['source_segment_id'], $data['shelf_id'], $data['layer'])) {
            Log::warning('⚠️ segment_copy sem campos obrigatórios', ['data' => $data]);

            return false;
        }

        $layer = $data['layer'];
        // Validações adicionais da layer
        if (! isset($layer['id'], $layer['segment_id'], $layer['product_id'])) {
            Log::warning('⚠️ segment_copy com layer incompleta', ['data' => $data]);

            return false;
        }

        // Busca tenant_id do usuário autenticado
        $tenantId = auth()->user()?->tenant_id ?? null;

        $oldSegment = $this->repository->find($data['source_segment_id']);
        if (! $oldSegment) {
            Log::warning('⚠️ segment_copy com source_segment_id inválido', ['source_segment_id' => $data['source_segment_id']]);

            return false;
        }

        // Cria o novo segment com os dados recebidos
        $segmentData = [
            'id' => $layer['segment_id'], // O novo segment_id vem da layer
            'shelf_id' => $data['shelf_id'],
            'ordering' => $oldSegment->ordering ?? 0,
            'quantity' => $oldSegment->quantity ?? 1,
        ];

        // Cria segment copiado
        $created = $this->create($segmentData, $tenantId);

        if (! $created) {
            Log::error('❌ Falha ao copiar segment', ['segment' => $segmentData]);

            return false;
        }

        // Log::info('✅ Segment copiado', [
        //     'source_segment_id' => $data['source_segment_id'],
        //     'new_segment_id' => $layer['segment_id'],
        //     'target_shelf_id' => $data['shelf_id'],
        // ]);

        // Filtra campos válidos da layer
        $layerFields = ['id', 'segment_id', 'product_id', 'height', 'alignment', 'spacing', 'quantity'];
        $layerData = array_intersect_key($layer, array_flip($layerFields));

        // Cria layer copiada usando o repository diretamente
        $layerCreated = $this->layerRepository->create(array_merge($layerData, [
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        if ($layerCreated) {
            // Log::info('✅ Layer copiada com segment', [
            //     'new_layer_id' => $layer['id'],
            //     'new_segment_id' => $layer['segment_id'],
            //     'product_id' => $layer['product_id'] ?? null,
            // ]);
        }

        return $created && $layerCreated;
    }

    /**
     * Transfere um segment para outra shelf (move, não copia)
     *
     * Frontend envia: { from_shelf_id, to_shelf_id, position }
     *
     * @param  array<string, mixed>  $data
     */
    public function transferSegment(string $segmentId, array $data): bool
    {
        // Validação: deve ter from_shelf_id e to_shelf_id
        if (! isset($data['from_shelf_id'], $data['to_shelf_id'])) {
            Log::warning('⚠️ segment_transfer sem campos obrigatórios', ['data' => $data]);

            return false;
        }

        // Atualiza apenas o shelf_id do segment (move para nova shelf)
        $updates = [
            'shelf_id' => $data['to_shelf_id'],
            'updated_at' => now(),
        ];

        // Opcionalmente atualiza position se fornecido
        if (isset($data['position'])) {
            $updates['ordering'] = $data['position'];
        }

        $updated = $this->repository->update($segmentId, $updates);

        if ($updated > 0) {
            // Log::info('✅ Segment transferido entre shelves', [
            //     'segment_id' => $segmentId,
            //     'from_shelf_id' => $data['from_shelf_id'],
            //     'to_shelf_id' => $data['to_shelf_id'],
            // ]);
        }

        return $updated > 0;
    }

    /**
     * Cria um segment para uma shelf
     *
     * @param  array<string, mixed>  $data
     */
    public function createForShelf(string $shelfId, array $data, ?string $tenantId): bool
    {
        return $this->repository->create(array_merge($data, [
            'shelf_id' => $shelfId,
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * Cria um segment com dados completos
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $tenantId): bool
    {
        return $this->repository->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * Reordena um segment dentro da mesma shelf (drag & drop para reordenação)
     *
     * Frontend envia: { shelf_id, ordering }
     *
     * @param  array<string, mixed>  $data
     */
    public function reorderSegment(string $segmentId, array $data): bool
    {
        // Validação: deve ter shelf_id e ordering
        if (! isset($data['shelf_id'], $data['ordering'])) {
            Log::warning('⚠️ segment_reorder sem campos obrigatórios', [
                'segment_id' => $segmentId,
                'data' => $data,
            ]);

            return false;
        }

        $updates = [
            'ordering' => $data['ordering'],
            'updated_at' => now(),
        ];

        $updated = $this->repository->update($segmentId, $updates);

        if ($updated > 0) {
            // Log::info('🔄 Segment reordenado', [
            //     'segment_id' => $segmentId,
            //     'shelf_id' => $data['shelf_id'],
            //     'new_ordering' => $data['ordering'],
            // ]);
        }

        return $updated > 0;
    }

    /**
     * Atualiza um segment
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $segmentId, array $data): bool
    {
        // Campos permitidos (inclui shelf_id para mover segmento entre shelves)
        $allowedFields = ['shelf_id', 'width', 'height', 'depth', 'position_x', 'position_y', 'ordering', 'alignment', 'spacing', 'quantity', 'deleted_at'];
        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updates)) {
            return false;
        }

        // Normaliza deleted_at
        if (isset($updates['deleted_at']) && is_string($updates['deleted_at'])) {
            $updates['deleted_at'] = Carbon::parse($updates['deleted_at'])->format('Y-m-d H:i:s');
        }

        $updates['updated_at'] = now();

        $updated = $this->repository->update($segmentId, $updates);

        // Log de movimentação entre shelves
        if ($updated > 0 && isset($updates['shelf_id'])) {
            // Log::info('📦 Segmento movido entre prateleiras', [
            //     'segment_id' => $segmentId,
            //     'new_shelf_id' => $updates['shelf_id'],
            // ]);
        }

        return $updated > 0;
    }
}
