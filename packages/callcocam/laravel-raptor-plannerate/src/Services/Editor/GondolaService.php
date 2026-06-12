<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Editor;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Gondolas
 */
class GondolaService
{
    use UsesPlannerateTenantDatabase;

    public function __construct(
        private ShelfStructureService $shelfStructure
    ) {}

    /**
     * Cria uma gôndola com módulos (seções) e prateleiras em uma transação.
     *
     * @param  array<string, mixed>  $data  Dados no formato do StoreGondolaRequest (camelCase): gondolaName, location, side, scaleFactor, flow, status, height, width, baseDepth, numModules, baseHeight, baseWidth, rackWidth, holeHeight, holeWidth, holeSpacing, numShelves, shelfWidth, shelfHeight, shelfDepth, productType
     */
    public function createGondolaWithStructure(Planogram $planogram, array $data): Gondola
    {
        return $this->plannerateTenantDatabase()->transaction(function () use ($planogram, $data) {
            $gondola = $this->createGondola($planogram, $data);
            $this->createSectionsWithShelves($gondola, $data);

            return $gondola;
        });
    }

    /**
     * Cria o registro da gôndola (sem seções).
     *
     * @param  array<string, mixed>  $data
     */
    protected function createGondola(Planogram $planogram, array $data): Gondola
    {
        $mode = $data['mode'] ?? 'manual';

        return Gondola::create([
            'planogram_id' => $planogram->id,
            'tenant_id' => $planogram->tenant_id,
            'name' => $data['gondolaName'] ?? '',
            'location' => $data['location'] ?? null,
            'side' => $data['side'] ?? '',
            'scale_factor' => $data['scaleFactor'] ?? 1,
            'flow' => $data['flow'] ?? 'left_to_right',
            'status' => $data['status'] ?? 'draft',
            'generation_mode' => $mode,
            'template_id' => $mode === 'template' ? ($data['template_id'] ?? null) : null,
        ]);
    }

    /**
     * Cria os módulos (seções) e prateleiras da gôndola.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createSectionsWithShelves(Gondola $gondola, array $data): void
    {
        $numModules = (int) ($data['numModules'] ?? 1);
        for ($i = 0; $i < $numModules; $i++) {
            $section = $this->createSection($gondola, $data, $i);
            $this->createShelvesForSection($section, $gondola, $data, $i);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createSection(Gondola $gondola, array $data, int $index): Section
    {
        return Section::create([
            'gondola_id' => $gondola->id,
            'name' => 'Módulo '.($index + 1),
            'code' => str(uniqid('SEC-'))->upper(),
            'ordering' => $index + 1,
            'width' => $data['width'] ?? 0,
            'height' => $data['height'] ?? 0,
            'base_height' => $data['baseHeight'] ?? 0,
            'base_width' => $data['baseWidth'] ?? 0,
            'base_depth' => $data['baseDepth'] ?? 0,
            'cremalheira_width' => $data['rackWidth'] ?? 4,
            'hole_height' => $data['holeHeight'] ?? 2,
            'hole_width' => $data['holeWidth'] ?? 2,
            'hole_spacing' => $data['holeSpacing'] ?? 2,
        ]);
    }

    /**
     * Cria as prateleiras de uma seção a partir de numShelves do formulário.
     * Delega a matemática de posicionamento ao ShelfStructureService (fonte compartilhada
     * com o modo automático).
     *
     * @param  array<string, mixed>  $data
     */
    protected function createShelvesForSection(Section $section, Gondola $gondola, array $data, int $sectionIndex): void
    {
        $numShelves = (int) ($data['numShelves'] ?? 0);

        $this->shelfStructure->createShelves($section, [
            'shelf_width' => $data['shelfWidth'] ?? 4,
            'shelf_height' => $data['shelfHeight'] ?? 4,
            'shelf_depth' => $data['shelfDepth'] ?? 40,
            'product_type' => $data['productType'] ?? 'normal',
        ], $numShelves);
    }

    /**
     * Cria ou atualiza uma gôndola baseado no tipo de mudança
     *
     * @param  array<string, mixed>  $change
     */
    public function createOrUpdate(array $change): bool
    {
        $gondolaId = $change['entityId'];
        $data = $change['data'];
        $type = $change['type'];

        return match ($type) {
            'gondola_update' => $this->update($gondolaId, $data),
            'gondola_scale' => $this->updateScale($gondolaId, $data),
            'gondola_alignment' => $this->updateAlignment($gondolaId, $data),
            'gondola_flow' => $this->updateFlow($gondolaId, $data),
            default => false
        };
    }

    /**
     * Atualiza propriedades gerais da gôndola
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $gondolaId, array $data): bool
    {
        $allowedFields = [
            'name',
            'status',
            'alignment',
            'scale_factor',
            'linked_map_gondola_id',
            'linked_map_gondola_category',
        ];
        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updates)) {
            return false;
        }

        $updates['updated_at'] = now();

        $updated = $this->plannerateTenantTable('gondolas')->where('id', $gondolaId)->update($updates);

        return $updated > 0;
    }

    /**
     * Atualiza fator de escala/zoom da gôndola
     *
     * @param  array<string, mixed>  $data
     */
    public function updateScale(string $gondolaId, array $data): bool
    {
        if (! isset($data['scale_factor'])) {
            return false;
        }

        $updated = $this->plannerateTenantTable('gondolas')->where('id', $gondolaId)->update([
            'scale_factor' => $data['scale_factor'],
            'updated_at' => now(),
        ]);
        Log::info('Updating scale', ['gondolaId' => $gondolaId, 'scale_factor' => $data['scale_factor']]);

        return $updated > 0;
    }

    /**
     * Atualiza alinhamento da gôndola
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAlignment(string $gondolaId, array $data): bool
    {
        if (! isset($data['alignment'])) {
            return false;
        }

        $updated = $this->plannerateTenantTable('gondolas')->where('id', $gondolaId)->update([
            'alignment' => $data['alignment'],
            'updated_at' => now(),
        ]);

        return $updated > 0;
    }

    /**
     * Atualiza fluxo de leitura da gôndola
     *
     * @param  array<string, mixed>  $data
     */
    public function updateFlow(string $gondolaId, array $data): bool
    {
        if (! isset($data['flow'])) {
            return false;
        }

        $updated = $this->plannerateTenantTable('gondolas')->where('id', $gondolaId)->update([
            'flow' => $data['flow'],
            'updated_at' => now(),
        ]);

        return $updated > 0;
    }
}
