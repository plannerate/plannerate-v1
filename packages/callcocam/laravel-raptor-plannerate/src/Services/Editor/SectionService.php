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
}
