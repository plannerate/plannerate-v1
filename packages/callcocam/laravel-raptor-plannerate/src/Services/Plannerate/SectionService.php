<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\GondolaRepository;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\SectionRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Sections (Seções/Módulos)
 */
class SectionService
{
    public function __construct(
        private SectionRepository $repository,
        private GondolaRepository $gondolaRepository
    ) {}

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

        // Verifica se section já existe
        $sectionExists = $this->repository->exists($sectionId);

        // Criação de nova section (aceita tanto section_create quanto section_update com _is_new)
        if (! $sectionExists || $type === 'section_create' || ($type === 'section_update' && isset($data['_is_new']))) {
            return $this->create($sectionId, $data);
        }
        $this->update($sectionId, $data);

        // Atualização
        return true;
    }

    /**
     * Cria nova section
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
        $gondola = $this->gondolaRepository->find($insertData['gondola_id']);
        if (! $gondola) {
            Log::warning('⚠️ Gondola não encontrada', ['gondola_id' => $insertData['gondola_id']]);

            return false;
        }

        // Insere nova section (code único para não violar sections_code_unique ao duplicar)
        $this->repository->create(array_merge($insertData, [
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
     * Atualiza uma section
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

        $updated = $this->repository->update($sectionId, $updates);

        if ($updated > 0) {
            // Reordena apenas quando a seção foi deletada; evitar reordenar em updates de ordering
            // impede que trocas de posição sejam revertidas por estados intermediários duplicados.
            $section = $this->repository->find($sectionId);
            if ($section && $section->gondola_id && isset($updates['deleted_at'])) {
                $this->reorderByOrdering($section->gondola_id);
            } else {
                if (isset($updates['ordering'])) {
                    $this->repository->update($sectionId, [
                        'name' => sprintf('Módulo %s', $updates['ordering']),
                    ]);
                }
            }
        }

        return $updated > 0;
    }

    /**
     * Reordena as seções de uma gôndola com base no campo ordering
     *
     * Atualiza o campo `ordering` sequencialmente (1, 2, 3, ...) baseado na ordem
     * crescente do campo `ordering` atual.
     *
     * @param  string  $gondolaId  ID da gôndola
     * @return int Número de seções reordenadas
     */
    public function reorderByOrdering(string $gondolaId): int
    {
        // Busca todas as seções da gôndola ordenadas por ordering
        $sections = $this->repository->findByGondolaId($gondolaId);

        if (empty($sections)) {
            Log::info('ℹ️ Nenhuma seção encontrada para reordenar', ['gondola_id' => $gondolaId]);

            return 0;
        }

        // Prepara array com id e novo ordering baseado na ordem atual
        $updates = [];
        $ordering = 1;

        foreach ($sections as $section) {
            // Só atualiza se o ordering estiver diferente
            if ($section->ordering != $ordering) {
                $updates[] = [
                    'id' => $section->id,
                    'name' => sprintf('Módulo %s', $ordering),
                    'ordering' => $ordering,
                ];
            }
            $ordering++;
        }

        // Atualiza em lote se houver mudanças
        if (! empty($updates)) {
            $this->repository->updateBatch($updates);

            Log::info('✅ Seções reordenadas', [
                'gondola_id' => $gondolaId,
                'total_sections' => count($sections),
                'updated' => count($updates),
            ]);

            return count($updates);
        }

        Log::info('ℹ️ Seções já estão ordenadas corretamente', ['gondola_id' => $gondolaId]);

        return 0;
    }
}
