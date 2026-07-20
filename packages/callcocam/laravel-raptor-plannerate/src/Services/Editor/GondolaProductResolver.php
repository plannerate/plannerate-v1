<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Editor;

use Callcocam\LaravelRaptorPlannerate\Models\Layer;

/**
 * Resolve quais produtos estão posicionados numa gôndola.
 *
 * Existe como serviço porque a resposta é consumida fora do editor (ex.: escopo do
 * link público de correção de dimensões) e a query é sutil o bastante para não valer
 * uma segunda cópia: os JOINs são de query builder cru, então o global scope de
 * SoftDeletes não se aplica e é preciso filtrar deleted_at em TODOS os níveis.
 * Remover um produto no editor soft-deleta apenas o segment, deixando a layer filha
 * com deleted_at NULL — sem os filtros completos o produto removido continua contando.
 */
class GondolaProductResolver
{
    /**
     * IDs distintos dos produtos atualmente posicionados na gôndola.
     *
     * @return list<string>
     */
    public function productIdsInGondola(string $gondolaId): array
    {
        if (trim($gondolaId) === '') {
            return [];
        }

        return Layer::query()
            ->join('segments', 'segments.id', '=', 'layers.segment_id')
            ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
            ->join('sections', 'sections.id', '=', 'shelves.section_id')
            ->where('sections.gondola_id', $gondolaId)
            ->whereNotNull('layers.product_id')
            ->whereNull('layers.deleted_at')
            ->whereNull('segments.deleted_at')
            ->whereNull('shelves.deleted_at')
            ->whereNull('sections.deleted_at')
            ->distinct()
            ->pluck('layers.product_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }
}
