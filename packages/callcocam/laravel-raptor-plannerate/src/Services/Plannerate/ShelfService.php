<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\LayerRepository;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\SectionRepository;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\SegmentRepository;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\ShelfRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Shelves (Prateleiras)
 */
class ShelfService
{
    public function __construct(
        private ShelfRepository $repository,
        private SectionRepository $sectionRepository,
        private SegmentRepository $segmentRepository,
        private LayerRepository $layerRepository
    ) {}

    /**
     * Cria ou atualiza uma shelf baseado no tipo de mudança
     *
     * @param  array<string, mixed>  $change
     */
    public function createOrUpdate(array $change): bool
    {
        $shelfId = $change['entityId'];
        $data = $change['data'];
        $type = $change['type'];
        // Verifica se shelf já existe
        $shelfExists = $this->repository->exists($shelfId);

        // Criação de nova shelf (aceita tanto shelf_create quanto shelf_update com _is_new)
        if (! $shelfExists || $type === 'shelf_create' || ($type === 'shelf_update' && isset($data['_is_new']))) {
            return $this->create($shelfId, $data);
        }

        // Atualização baseada no tipo de mudança
        return match ($type) {
            'shelf_move' => $this->move($shelfId, $data),
            'shelf_transfer' => $this->transfer($shelfId, $data),
            'shelf_update' => $this->update($shelfId, $data),
            default => false
        };
    }

    /**
     * Cria nova shelf
     *
     * @param  array<string, mixed>  $data
     */
    public function create(string $shelfId, array $data): bool
    {
        // Campos permitidos para criação
        $allowedFields = ['section_id', 'shelf_position', 'shelf_width', 'shelf_height', 'shelf_depth', 'product_type', 'ordering', 'alignment', 'spacing'];
        $insertData = array_intersect_key($data, array_flip($allowedFields));

        // Validação: section_id é obrigatória
        if (! isset($insertData['section_id'])) {
            Log::warning('⚠️ Shelf creation: section_id obrigatória', ['data' => $data]);

            return false;
        }

        // Busca section para obter tenant_id
        $section = $this->sectionRepository->find($insertData['section_id']);
        if (! $section) {
            Log::warning('⚠️ Section não encontrada', ['section_id' => $insertData['section_id']]);

            return false;
        }

        // Insere nova shelf
        $this->repository->create(array_merge($insertData, [
            'id' => $shelfId,
            'code' => strtoupper(uniqid('SHELF-')),
            'tenant_id' => $section->tenant_id ?? null,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        Log::info('✅ Shelf criada', ['shelf_id' => $shelfId, 'section_id' => $insertData['section_id']]);

        // Se tiver segments no payload, cria os relacionamentos
        if (isset($data['segments']) && is_array($data['segments'])) {
            $this->createShelfSegments($shelfId, $data['segments'], $section->tenant_id ?? null);
        }

        // Reordena as prateleiras da seção após criar
        $this->reorderByPosition($insertData['section_id']);

        return true;
    }

    /**
     * Cria segments e layers para uma shelf recém-criada
     *
     * @param  array<int, array<string, mixed>>  $segments  Array de segments do payload
     */
    private function createShelfSegments(string $shelfId, array $segments, ?string $tenantId): void
    {
        foreach ($segments as $segmentData) {
            // Validação básica do segment
            if (! isset($segmentData['id'])) {
                Log::warning('⚠️ Segment sem ID ao criar shelf', ['shelf_id' => $shelfId]);

                continue;
            }

            // Filtra campos válidos do segment
            $segmentFields = ['id', 'width', 'height', 'ordering', 'alignment', 'spacing', 'quantity'];
            $segmentInsert = array_intersect_key($segmentData, array_flip($segmentFields));

            // Cria segment vinculado à shelf
            $this->segmentRepository->create(array_merge($segmentInsert, [
                'shelf_id' => $shelfId,
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            Log::info('✅ Segment criado para shelf', [
                'segment_id' => $segmentData['id'],
                'shelf_id' => $shelfId,
            ]);

            // Se o segment tiver layer, cria também
            if (isset($segmentData['layer']) && is_array($segmentData['layer'])) {
                $layerData = $segmentData['layer'];

                // Validação: layer precisa de ID e product_id
                if (! isset($layerData['id']) || ! isset($layerData['product_id'])) {
                    Log::warning('⚠️ Layer sem ID ou product_id ao criar shelf', [
                        'shelf_id' => $shelfId,
                        'segment_id' => $segmentData['id'],
                    ]);

                    continue;
                }

                // Filtra campos válidos da layer
                $layerFields = ['id', 'product_id', 'height', 'alignment', 'spacing', 'quantity'];
                $layerInsert = array_intersect_key($layerData, array_flip($layerFields));

                // Cria layer vinculada ao segment
                $this->layerRepository->create(array_merge($layerInsert, [
                    'segment_id' => $segmentData['id'],
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                Log::info('✅ Layer criada para segment', [
                    'layer_id' => $layerData['id'],
                    'segment_id' => $segmentData['id'],
                    'product_id' => $layerData['product_id'],
                ]);
            }
        }
    }

    /**
     * Move shelf dentro da mesma seção (altera position)
     *
     * @param  array<string, mixed>  $data
     */
    public function move(string $shelfId, array $data): bool
    {
        if (! isset($data['shelf_position'])) {
            return false;
        }

        // Busca a prateleira para obter a section_id antes de atualizar
        $shelf = $this->repository->find($shelfId);
        if (! $shelf || ! $shelf->section_id) {
            return false;
        }

        $updated = $this->repository->update($shelfId, [
            'shelf_position' => $data['shelf_position'],
            'updated_at' => now(),
        ]);

        if ($updated > 0) {
            // Reordena as prateleiras da seção após mover
            $this->reorderByPosition($shelf->section_id);
        }

        return $updated > 0;
    }

    /**
     * Transfere shelf entre seções
     *
     * @param  array<string, mixed>  $data
     */
    public function transfer(string $shelfId, array $data): bool
    {
        if (! isset($data['to_section_id'])) {
            return false;
        }

        // Busca a prateleira para obter a section_id de origem
        $shelf = $this->repository->find($shelfId);
        if (! $shelf) {
            return false;
        }

        $oldSectionId = $shelf->section_id;
        $newSectionId = $data['to_section_id'];

        $updates = [
            'section_id' => $newSectionId,
            'updated_at' => now(),
        ];

        // Opcionalmente atualiza position e ordering
        if (isset($data['shelf_position'])) {
            $updates['shelf_position'] = $data['shelf_position'];
        }
        if (isset($data['ordering'])) {
            $updates['ordering'] = $data['ordering'];
        }

        $updated = $this->repository->update($shelfId, $updates);

        if ($updated > 0) {
            Log::info('📦 Shelf transferida', [
                'shelf_id' => $shelfId,
                'from_section_id' => $oldSectionId,
                'to_section_id' => $newSectionId,
            ]);

            // Reordena as prateleiras da seção de origem (se existir)
            if ($oldSectionId) {
                $this->reorderByPosition($oldSectionId);
            }

            // Reordena as prateleiras da seção de destino
            $this->reorderByPosition($newSectionId);
        }

        return $updated > 0;
    }

    /**
     * Atualiza propriedades da shelf
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $shelfId, array $data): bool
    {
        $allowedFields = ['shelf_position', 'shelf_width', 'shelf_height', 'shelf_depth', 'product_type', 'ordering', 'alignment', 'spacing', 'deleted_at'];
        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updates)) {
            return false;
        }

        // Busca a prateleira para obter a section_id antes de atualizar
        $shelf = $this->repository->find($shelfId);
        if (! $shelf || ! $shelf->section_id) {
            return false;
        }

        $sectionId = $shelf->section_id;
        $positionChanged = isset($updates['shelf_position']) && $updates['shelf_position'] != $shelf->shelf_position;

        // Normaliza deleted_at
        if (isset($updates['deleted_at']) && is_string($updates['deleted_at'])) {
            $updates['deleted_at'] = Carbon::parse($updates['deleted_at'])->format('Y-m-d H:i:s');
        }

        $updates['updated_at'] = now();

        $updated = $this->repository->update($shelfId, $updates);

        if ($updated > 0 && $positionChanged) {
            // Reordena as prateleiras da seção se a position foi alterada
            $this->reorderByPosition($sectionId);
        }

        return $updated > 0;
    }

    /**
     * Reordena as prateleiras de uma seção com base na shelf_position
     *
     * Atualiza o campo `ordering` sequencialmente (1, 2, 3, ...) baseado na ordem
     * crescente de `shelf_position` (de baixo para cima).
     *
     * @param  string  $sectionId  ID da seção
     * @return int Número de prateleiras reordenadas
     */
    public function reorderByPosition(string $sectionId): int
    {
        // Busca todas as prateleiras da seção ordenadas por shelf_position
        $shelves = $this->repository->findBySectionId($sectionId);

        if (empty($shelves)) {
            Log::info('ℹ️ Nenhuma prateleira encontrada para reordenar', ['section_id' => $sectionId]);

            return 0;
        }

        // Prepara array com id e novo ordering baseado na posição
        $updates = [];
        $ordering = 1;

        foreach ($shelves as $shelf) {
            // Só atualiza se o ordering estiver diferente
            if ($shelf->ordering != $ordering) {
                $updates[] = [
                    'id' => $shelf->id,
                    'ordering' => $ordering,
                ];
            }
            $ordering++;
        }

        // Atualiza em lote se houver mudanças
        if (! empty($updates)) {
            $this->repository->updateBatch($updates);

            // Log::info('✅ Prateleiras reordenadas', [
            //     'section_id' => $sectionId,
            //     'total_shelves' => count($shelves),
            //     'updated' => count($updates),
            // ]);

            return count($updates);
        }

        return 0;
    }
}
