<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Editor;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Events\LayerRemovedEvent;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Segments (Segmentos).
 *
 * Acessa o banco tenant diretamente via UsesPlannerateTenantDatabase — a antiga
 * camada de Repositories foi absorvida aqui (era um wrapper fino de query builder).
 */
class SegmentService
{
    use UsesPlannerateTenantDatabase;

    /**
     * Cria ou atualiza um segment baseado no tipo de mudança.
     *
     * O array $change deve conter 'gondolaId' para que o soft-delete de segmentos
     * dispare LayerRemovedEvent para cada layer filha.
     *
     * @param  array<string, mixed>  $change
     */
    public function createOrUpdate(array $change): bool
    {
        $type = $change['type'];

        return match ($type) {
            'segment_copy' => $this->copySegment($change['entityId'], $change['data']),
            'segment_transfer' => $this->transferSegment($change['entityId'], $change['data']),
            'segment_reorder' => $this->reorderSegment($change['entityId'], $change['data']),
            'segment_update' => $this->update($change['entityId'], $change['data'], $change['gondolaId'] ?? null),
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
    public function copySegment(string $segmentId, array $data): bool
    {
        // Validação: deve ter source_segment_id, shelf_id e layer
        if (! isset($data['source_segment_id'], $data['shelf_id'], $data['layer'])) {
            Log::warning('⚠️ segment_copy sem campos obrigatórios', ['data' => $data]);

            return false;
        }

        $layer = $data['layer'];
        $newSegmentId = $segmentId !== '' ? $segmentId : ($layer['segment_id'] ?? null);

        // Validações adicionais da layer
        if (! isset($layer['id'], $layer['product_id']) || ! is_string($newSegmentId) || $newSegmentId === '') {
            Log::warning('⚠️ segment_copy com layer incompleta', ['data' => $data]);

            return false;
        }

        $oldSegment = $this->plannerateTenantTable('segments')->where('id', $data['source_segment_id'])->first();
        if (! $oldSegment) {
            Log::warning('⚠️ segment_copy com source_segment_id inválido', ['source_segment_id' => $data['source_segment_id']]);

            return false;
        }

        $targetShelf = $this->plannerateTenantTable('shelves')->where('id', $data['shelf_id'])->first();
        if (! $targetShelf) {
            Log::warning('⚠️ segment_copy com shelf_id inválido', ['shelf_id' => $data['shelf_id']]);

            return false;
        }

        $position = $data['position'] ?? $oldSegment->ordering ?? 0;
        $tenantId = $targetShelf->tenant_id ?? null;

        // Cria o novo segment com os dados recebidos
        $created = $this->create([
            'id' => $newSegmentId,
            'shelf_id' => $data['shelf_id'],
            'ordering' => $position,
            'position' => $position,
            'quantity' => $oldSegment->quantity ?? 1,
        ], $tenantId);

        if (! $created) {
            Log::error('❌ Falha ao copiar segment', ['segment_id' => $newSegmentId]);

            return false;
        }

        // Filtra campos válidos da layer e cria a cópia vinculada ao novo segment
        $layerFields = ['id', 'segment_id', 'product_id', 'height', 'alignment', 'spacing', 'quantity'];
        $layerData = array_intersect_key($layer, array_flip($layerFields));
        $layerData['segment_id'] = $newSegmentId;

        $layerCreated = $this->plannerateTenantTable('layers')->insert(array_merge($layerData, [
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

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

        $updated = $this->plannerateTenantTable('segments')->where('id', $segmentId)->update($updates);

        return $updated > 0;
    }

    /**
     * Cria um segment com dados completos
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $tenantId): bool
    {
        return $this->plannerateTenantTable('segments')->insert(array_merge($data, [
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

        $updated = $this->plannerateTenantTable('segments')->where('id', $segmentId)->update([
            'ordering' => $data['ordering'],
            'updated_at' => now(),
        ]);

        return $updated > 0;
    }

    /**
     * Atualiza um segment.
     *
     * Quando deleted_at é definido (soft delete = remoção de produto pelo usuário),
     * busca todas as layers filhas do segment e dispara LayerRemovedEvent para cada uma —
     * permitindo que listeners externos (ex.: inserção em rejeitados) reajam corretamente.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $segmentId, array $data, ?string $gondolaId = null): bool
    {
        // Campos permitidos (inclui shelf_id para mover segmento entre shelves)
        $allowedFields = ['shelf_id', 'width', 'height', 'ordering', 'alignment', 'spacing', 'quantity', 'deleted_at'];
        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updates)) {
            return false;
        }

        // Verifica se é um soft delete antes de aplicar
        $isBeingRemoved = isset($updates['deleted_at']) && $updates['deleted_at'] !== null;

        // Normaliza deleted_at
        if (isset($updates['deleted_at']) && is_string($updates['deleted_at'])) {
            $updates['deleted_at'] = Carbon::parse($updates['deleted_at'])->format('Y-m-d H:i:s');
        }

        $updates['updated_at'] = now();

        $updated = $this->plannerateTenantTable('segments')->where('id', $segmentId)->update($updates);

        // Ao soft-deletar o segment, dispara evento para cada layer filha
        if ($updated > 0 && $isBeingRemoved) {
            $this->dispatchEventsForSegmentLayers($segmentId, $gondolaId);
        }

        return $updated > 0;
    }

    /**
     * Busca todas as layers do segment e dispara LayerRemovedEvent para cada uma.
     *
     * Sem gôndola válida o evento não é disparado — não interrompe o fluxo principal.
     */
    private function dispatchEventsForSegmentLayers(string $segmentId, ?string $gondolaId): void
    {
        if ($gondolaId === null || $gondolaId === '') {
            return;
        }

        $gondola = Gondola::query()->find($gondolaId);

        if (! $gondola) {
            return;
        }

        $layers = $this->plannerateTenantTable('layers')
            ->where('segment_id', $segmentId)
            ->get();

        foreach ($layers as $layer) {
            LayerRemovedEvent::dispatch($layer, $gondola);
        }
    }
}
