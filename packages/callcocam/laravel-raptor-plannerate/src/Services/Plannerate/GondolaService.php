<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\GondolaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Gondolas
 */
class GondolaService
{
    public function __construct(
        private GondolaRepository $repository
    ) {}

    /**
     * Cria uma gôndola com módulos (seções) e prateleiras em uma transação.
     *
     * @param  array<string, mixed>  $data  Dados no formato do StoreGondolaRequest (camelCase): gondolaName, location, side, scaleFactor, flow, status, height, width, baseDepth, numModules, baseHeight, baseWidth, rackWidth, holeHeight, holeWidth, holeSpacing, numShelves, shelfWidth, shelfHeight, shelfDepth, productType
     */
    public function createGondolaWithStructure(Planogram $planogram, array $data): Gondola
    {
        return DB::transaction(function () use ($planogram, $data) {
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
        return Gondola::create([
            'planogram_id' => $planogram->id,
            'tenant_id' => $planogram->tenant_id,
            'name' => $data['gondolaName'] ?? '',
            'location' => $data['location'] ?? null,
            'side' => $data['side'] ?? '',
            'scale_factor' => $data['scaleFactor'] ?? 1,
            'flow' => $data['flow'] ?? 'left_to_right',
            'status' => $data['status'] ?? 'draft',
            'height' => $data['height'] ?? 0,
            'width' => $data['width'] ?? 0,
            'depth' => $data['baseDepth'] ?? 0,
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
     * @param  array<string, mixed>  $data
     */
    protected function createShelvesForSection(Section $section, Gondola $gondola, array $data, int $sectionIndex): void
    {
        $numShelves = (int) ($data['numShelves'] ?? 0);
        if ($numShelves <= 0) {
            return;
        }

        $availableHeight = (float) (($data['height'] ?? 0) - ($data['baseHeight'] ?? 0));
        $shelfPositions = $this->calculateShelfPositions(
            $availableHeight,
            0,
            $numShelves,
            (float) ($data['holeSpacing'] ?? 2),
            (float) ($data['holeHeight'] ?? 2)
        );

        foreach ($shelfPositions as $index => $position) {
            $section->shelves()->create([
                'code' => str(uniqid('SHELF-'))->upper(),
                'ordering' => $index + 1,
                'shelf_position' => $position,
                'shelf_width' => $data['shelfWidth'] ?? 4,
                'shelf_height' => $data['shelfHeight'] ?? 4,
                'shelf_depth' => $data['shelfDepth'] ?? 40,
                'product_type' => $data['productType'] ?? 'normal',
            ]);
        }
    }

    /**
     * @return array<int, float>
     */
    protected function calculateShelfPositions(
        float $totalHeight,
        float $baseHeight,
        int $numShelves,
        float $holeSpacing,
        float $holeHeight
    ): array {
        if ($numShelves <= 0) {
            return [];
        }

        $holes = $this->buildHolePositions($baseHeight, $totalHeight, $holeSpacing, $holeHeight);
        if (empty($holes)) {
            return [];
        }

        if ($numShelves === 1) {
            $middle = $holes[(int) floor(count($holes) / 2)];

            return [$middle];
        }

        if ($numShelves === 2) {
            return [$holes[0], $holes[count($holes) - 1]];
        }

        $positions = [$holes[0]];
        $first = $holes[0];
        $last = $holes[count($holes) - 1];
        $step = ($last - $first) / ($numShelves - 1);

        for ($i = 1; $i < $numShelves - 1; $i++) {
            $ideal = $first + ($step * $i);
            $positions[] = $this->closestHole($ideal, $holes);
        }
        $positions[] = $last;
        sort($positions);

        return $positions;
    }

    /**
     * @return array<int, float>
     */
    protected function buildHolePositions(float $baseHeight, float $totalHeight, float $holeSpacing, float $holeHeight): array
    {
        $holes = [];
        $current = $baseHeight;
        while ($current <= $totalHeight) {
            $holes[] = $current;
            $current += $holeSpacing;
        }
        if (end($holes) !== $totalHeight) {
            $holes[] = $totalHeight;
        }

        return $holes;
    }

    protected function closestHole(float $ideal, array $holes): float
    {
        $closest = $holes[0];
        $min = abs($ideal - $closest);
        foreach ($holes as $hole) {
            $dist = abs($ideal - $hole);
            if ($dist < $min) {
                $min = $dist;
                $closest = $hole;
            }
        }

        return $closest;
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
            'description',
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

        $updated = $this->repository->update($gondolaId, $updates);

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

        $updated = $this->repository->update($gondolaId, [
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

        $updated = $this->repository->update($gondolaId, [
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

        $updated = $this->repository->update($gondolaId, [
            'flow' => $data['flow'],
            'updated_at' => now(),
        ]);

        return $updated > 0;
    }
}
