<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Editor;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Shelves (Prateleiras).
 *
 * Acessa o banco tenant diretamente via UsesPlannerateTenantDatabase — a antiga
 * camada de Repositories foi absorvida aqui (era um wrapper fino de query builder).
 */
class ShelfService
{
    use UsesPlannerateTenantDatabase;

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

        $shelfExists = $this->plannerateTenantTable('shelves')->where('id', $shelfId)->exists();

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
     * Cria nova shelf (id é o ULID gerado no frontend)
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
        $section = $this->plannerateTenantTable('sections')->where('id', $insertData['section_id'])->first();
        if (! $section) {
            Log::warning('⚠️ Section não encontrada', ['section_id' => $insertData['section_id']]);

            return false;
        }

        $this->plannerateTenantTable('shelves')->insert(array_merge($insertData, [
            'id' => $shelfId,
            'code' => strtoupper(uniqid('SHELF-')),
            'tenant_id' => $section->tenant_id ?? null,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        Log::info('✅ Shelf criada', ['shelf_id' => $shelfId, 'section_id' => $insertData['section_id']]);

        // Se tiver segments no payload (duplicação de prateleira/seção), cria a hierarquia
        if (isset($data['segments']) && is_array($data['segments'])) {
            $this->createShelfSegments($shelfId, $data['segments'], $section->tenant_id ?? null);
        }

        // Reordena as prateleiras da seção após criar
        $this->reorderByPosition($insertData['section_id']);

        return true;
    }

    /**
     * Cria segments e layers para uma shelf recém-criada (duplicação completa)
     *
     * @param  array<int, array<string, mixed>>  $segments  Array de segments do payload
     */
    private function createShelfSegments(string $shelfId, array $segments, ?string $tenantId): void
    {
        foreach ($segments as $segmentData) {
            if (! isset($segmentData['id'])) {
                Log::warning('⚠️ Segment sem ID ao criar shelf', ['shelf_id' => $shelfId]);

                continue;
            }

            $segmentFields = ['id', 'width', 'height', 'ordering', 'alignment', 'spacing', 'quantity'];
            $segmentInsert = array_intersect_key($segmentData, array_flip($segmentFields));

            $this->plannerateTenantTable('segments')->insert(array_merge($segmentInsert, [
                'shelf_id' => $shelfId,
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            // Se o segment tiver layer, cria também
            if (isset($segmentData['layer']) && is_array($segmentData['layer'])) {
                $layerData = $segmentData['layer'];

                if (! isset($layerData['id']) || ! isset($layerData['product_id'])) {
                    Log::warning('⚠️ Layer sem ID ou product_id ao criar shelf', [
                        'shelf_id' => $shelfId,
                        'segment_id' => $segmentData['id'],
                    ]);

                    continue;
                }

                $layerFields = ['id', 'product_id', 'height', 'alignment', 'spacing', 'quantity'];
                $layerInsert = array_intersect_key($layerData, array_flip($layerFields));

                $this->plannerateTenantTable('layers')->insert(array_merge($layerInsert, [
                    'segment_id' => $segmentData['id'],
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
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
        $shelf = $this->plannerateTenantTable('shelves')->where('id', $shelfId)->first();
        if (! $shelf || ! $shelf->section_id) {
            return false;
        }

        $updated = $this->plannerateTenantTable('shelves')->where('id', $shelfId)->update([
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
        $shelf = $this->plannerateTenantTable('shelves')->where('id', $shelfId)->first();
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

        $updated = $this->plannerateTenantTable('shelves')->where('id', $shelfId)->update($updates);

        if ($updated > 0) {
            Log::info('📦 Shelf transferida', [
                'shelf_id' => $shelfId,
                'from_section_id' => $oldSectionId,
                'to_section_id' => $newSectionId,
            ]);

            // Reordena as prateleiras das seções de origem e destino
            if ($oldSectionId) {
                $this->reorderByPosition($oldSectionId);
            }

            $this->reorderByPosition($newSectionId);
        }

        return $updated > 0;
    }

    /**
     * Atualiza propriedades da shelf (inclui soft delete via deleted_at)
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
        $shelf = $this->plannerateTenantTable('shelves')->where('id', $shelfId)->first();
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

        $updated = $this->plannerateTenantTable('shelves')->where('id', $shelfId)->update($updates);

        if ($updated > 0 && $positionChanged) {
            // Reordena as prateleiras da seção se a position foi alterada
            $this->reorderByPosition($sectionId);
        }

        return $updated > 0;
    }

    /**
     * Reordena as prateleiras de uma seção com base na shelf_position.
     *
     * Atualiza o campo `ordering` sequencialmente (1, 2, 3, ...) baseado na ordem
     * crescente de `shelf_position` (de baixo para cima).
     *
     * @return int Número de prateleiras reordenadas
     */
    public function reorderByPosition(string $sectionId): int
    {
        $shelves = $this->plannerateTenantTable('shelves')
            ->where('section_id', $sectionId)
            ->whereNull('deleted_at')
            ->orderBy('shelf_position', 'asc')
            ->get();

        if ($shelves->isEmpty()) {
            return 0;
        }

        $updatedCount = 0;
        $ordering = 1;

        foreach ($shelves as $shelf) {
            // Só atualiza se o ordering estiver diferente
            if ($shelf->ordering != $ordering) {
                $this->plannerateTenantTable('shelves')->where('id', $shelf->id)->update([
                    'ordering' => $ordering,
                    'updated_at' => now(),
                ]);
                $updatedCount++;
            }
            $ordering++;
        }

        return $updatedCount;
    }
}
