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
use Illuminate\Support\Str;

/**
 * Service para operações de negócio relacionadas a Sections (Módulos).
 *
 * Acessa o banco tenant diretamente via UsesPlannerateTenantDatabase — a antiga
 * camada de Repositories foi absorvida aqui (era um wrapper fino de query builder).
 */
class SectionService
{
    use UsesPlannerateTenantDatabase;

    /**
     * Cria ou atualiza uma section baseado no tipo de mudança
     *
     * @param  array<string, mixed>  $change
     */
    public function createOrUpdate(array $change): bool
    {
        $sectionId = $change['entityId'];
        $data = $change['data'];
        $type = $change['type'];

        $sectionExists = $this->plannerateTenantTable('sections')->where('id', $sectionId)->exists();

        // Criação de nova section (aceita tanto section_create quanto section_update com _is_new)
        if (! $sectionExists || $type === 'section_create' || ($type === 'section_update' && isset($data['_is_new']))) {
            return $this->create($sectionId, $data);
        }

        $this->update($sectionId, $data);

        return true;
    }

    /**
     * Cria nova section (id é o ULID gerado no frontend)
     *
     * @param  array<string, mixed>  $data
     */
    public function create(string $sectionId, array $data): bool
    {
        // Campos permitidos para criação
        $allowedFields = [
            'gondola_id',
            'name',
            'code',
            'width',
            'height',
            'num_shelves',
            'base_height',
            'base_depth',
            'base_width',
            'cremalheira_width',
            'hole_height',
            'hole_width',
            'hole_spacing',
            'ordering',
            'alignment',
        ];
        $insertData = array_intersect_key($data, array_flip($allowedFields));

        // Validação: gondola_id é obrigatória
        if (! isset($insertData['gondola_id'])) {
            Log::warning('⚠️ Section creation: gondola_id obrigatória', ['data' => $data]);

            return false;
        }

        // Busca gondola para obter tenant_id
        $gondola = $this->plannerateTenantTable('gondolas')->where('id', $insertData['gondola_id'])->first();
        if (! $gondola) {
            Log::warning('⚠️ Gondola não encontrada', ['gondola_id' => $insertData['gondola_id']]);

            return false;
        }

        // Insere nova section (code único para não violar sections_code_unique ao duplicar)
        $this->plannerateTenantTable('sections')->insert(array_merge($insertData, [
            'id' => $sectionId,
            'code' => 'SEC-'.strtoupper(substr($sectionId, -10)),
            'tenant_id' => $gondola->tenant_id ?? null,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        Log::info('✅ Section criada', ['section_id' => $sectionId, 'gondola_id' => $insertData['gondola_id']]);

        // Reordena as seções da gôndola após criar
        $this->reorderByOrdering($insertData['gondola_id']);

        return true;
    }

    /**
     * Atualiza uma section (inclui soft delete via deleted_at).
     *
     * Quando o ordering muda, o nome é regravado como "Módulo {n}" — as seções
     * são nomeadas pela posição (contrato atual do editor).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $sectionId, array $data): bool
    {
        // Campos permitidos
        $allowedFields = [
            'name',
            'code',
            'width',
            'height',
            'num_shelves',
            'base_height',
            'base_depth',
            'base_width',
            'cremalheira_width',
            'hole_height',
            'hole_width',
            'hole_spacing',
            'ordering',
            'alignment',
            'deleted_at',
        ];
        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updates)) {
            return false;
        }

        // Normaliza deleted_at
        if (isset($updates['deleted_at']) && is_string($updates['deleted_at'])) {
            $updates['deleted_at'] = Carbon::parse($updates['deleted_at'])->format('Y-m-d H:i:s');
        }

        $updates['updated_at'] = now();

        $updated = $this->plannerateTenantTable('sections')->where('id', $sectionId)->update($updates);

        if ($updated > 0) {
            // Reordena apenas quando a seção foi deletada; evitar reordenar em updates de ordering
            // impede que trocas de posição sejam revertidas por estados intermediários duplicados.
            $section = $this->plannerateTenantTable('sections')->where('id', $sectionId)->first();
            if ($section && $section->gondola_id && isset($updates['deleted_at'])) {
                $this->reorderByOrdering($section->gondola_id);
            } elseif (isset($updates['ordering'])) {
                $this->plannerateTenantTable('sections')->where('id', $sectionId)->update([
                    'name' => sprintf('Módulo %s', $updates['ordering']),
                ]);
            }
        }

        return $updated > 0;
    }

    /**
     * Reordena as seções de uma gôndola com base no campo ordering.
     *
     * Atualiza `ordering` sequencialmente (1, 2, 3, ...). O nome NÃO é alterado
     * aqui — o rename "Módulo {n}" só acontece em update() quando o ordering é
     * alterado explicitamente pelo usuário (comportamento original preservado).
     *
     * @return int Número de seções reordenadas
     */
    public function reorderByOrdering(string $gondolaId): int
    {
        $sections = $this->plannerateTenantTable('sections')
            ->where('gondola_id', $gondolaId)
            ->whereNull('deleted_at')
            ->orderBy('ordering', 'asc')
            ->get();

        if ($sections->isEmpty()) {
            return 0;
        }

        $updatedCount = 0;
        $ordering = 1;

        foreach ($sections as $section) {
            // Só atualiza se o ordering estiver diferente
            if ($section->ordering != $ordering) {
                $this->plannerateTenantTable('sections')->where('id', $section->id)->update([
                    'ordering' => $ordering,
                    'updated_at' => now(),
                ]);
                $updatedCount++;
            }
            $ordering++;
        }

        if ($updatedCount > 0) {
            Log::info('✅ Módulos reordenados', [
                'gondola_id' => $gondolaId,
                'total_sections' => $sections->count(),
                'updated' => $updatedCount,
            ]);
        }

        return $updatedCount;
    }

    /**
     * Copia (deep copy) uma section inteira — com prateleiras, segmentos e
     * camadas — para outra gôndola, no fim da ordenação de destino.
     *
     * Gera novos ULIDs para section/shelf/segment/layer e regrava os campos
     * `code`/`slug` únicos; o `product_id` das camadas é PRESERVADO (o produto
     * é referência, não uma cópia — mesma regra do copySegmentToShelf no editor).
     * Copia apenas linhas ativas (não deletadas). Deve rodar dentro de uma
     * transação do banco tenant (o controller abre/commita).
     *
     * @return string ULID da nova section
     */
    public function deepCopyToGondola(string $sourceSectionId, string $targetGondolaId): string
    {
        $source = $this->plannerateTenantTable('sections')->where('id', $sourceSectionId)->first();

        if (! $source) {
            throw new \RuntimeException("Seção de origem não encontrada: {$sourceSectionId}");
        }

        $gondola = $this->plannerateTenantTable('gondolas')->where('id', $targetGondolaId)->first();

        if (! $gondola) {
            throw new \RuntimeException("Gôndola de destino não encontrada: {$targetGondolaId}");
        }

        $now = now();
        $tenantId = $gondola->tenant_id ?? null;
        $userId = auth()->id();

        // 1) Nova section no fim da gôndola de destino
        $newSectionId = $this->newUlid();
        $maxOrdering = $this->plannerateTenantTable('sections')->where('gondola_id', $targetGondolaId)->max('ordering') ?? 0;

        $this->plannerateTenantTable('sections')->insert(array_merge((array) $source, [
            'id' => $newSectionId,
            'gondola_id' => $targetGondolaId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'ordering' => $maxOrdering + 1,
            'code' => 'SEC-'.strtoupper(substr($newSectionId, -10)),
            'slug' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]));

        // 2) Prateleiras
        $shelves = $this->plannerateTenantTable('shelves')
            ->where('section_id', $sourceSectionId)
            ->whereNull('deleted_at')
            ->get();

        $shelfIdMap = [];
        $shelfRows = [];

        foreach ($shelves as $shelf) {
            $newShelfId = $this->newUlid();
            $shelfIdMap[$shelf->id] = $newShelfId;
            $shelfRows[] = array_merge((array) $shelf, [
                'id' => $newShelfId,
                'section_id' => $newSectionId,
                'tenant_id' => $tenantId,
                'code' => 'SHF-'.strtoupper(substr($newShelfId, -10)),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        if (! empty($shelfRows)) {
            $this->plannerateTenantTable('shelves')->insert($shelfRows);
        }

        // 3) Segmentos
        $segmentIdMap = [];
        $segmentRows = [];

        if (! empty($shelfIdMap)) {
            $segments = $this->plannerateTenantTable('segments')
                ->whereIn('shelf_id', array_keys($shelfIdMap))
                ->whereNull('deleted_at')
                ->get();

            foreach ($segments as $segment) {
                $newSegmentId = $this->newUlid();
                $segmentIdMap[$segment->id] = $newSegmentId;
                $segmentRows[] = array_merge((array) $segment, [
                    'id' => $newSegmentId,
                    'shelf_id' => $shelfIdMap[$segment->shelf_id],
                    'tenant_id' => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            }

            if (! empty($segmentRows)) {
                $this->plannerateTenantTable('segments')->insert($segmentRows);
            }
        }

        // 4) Camadas (mantém product_id — produto é referência, não cópia)
        $layerRows = [];

        if (! empty($segmentIdMap)) {
            $layers = $this->plannerateTenantTable('layers')
                ->whereIn('segment_id', array_keys($segmentIdMap))
                ->whereNull('deleted_at')
                ->get();

            foreach ($layers as $layer) {
                $layerRows[] = array_merge((array) $layer, [
                    'id' => $this->newUlid(),
                    'segment_id' => $segmentIdMap[$layer->segment_id],
                    'tenant_id' => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            }

            if (! empty($layerRows)) {
                $this->plannerateTenantTable('layers')->insert($layerRows);
            }
        }

        $this->reorderByOrdering($targetGondolaId);

        Log::info('✅ Módulo copiado (deep copy)', [
            'source_section_id' => $sourceSectionId,
            'new_section_id' => $newSectionId,
            'target_gondola_id' => $targetGondolaId,
            'shelves' => count($shelfRows),
            'segments' => count($segmentRows),
            'layers' => count($layerRows),
        ]);

        return $newSectionId;
    }

    /**
     * Gera um ULID em minúsculas (mesma convenção do trait HasUlids dos models).
     */
    protected function newUlid(): string
    {
        return strtolower((string) Str::ulid());
    }
}
