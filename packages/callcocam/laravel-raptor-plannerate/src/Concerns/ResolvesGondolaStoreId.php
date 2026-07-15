<?php

namespace Callcocam\LaravelRaptorPlannerate\Concerns;

use Callcocam\LaravelRaptorPlannerate\Models\Gondola;

/**
 * Resolve a loja do planograma de uma gôndola: direta ou, para planogramas de
 * cluster, a loja do cluster (mesma regra de
 * AutoPlanogram\ProductSelectionService::resolveStoreIdForAssortment).
 *
 * Sem isso, consultas de vendas por gôndola somam todas as lojas do tenant em
 * vez de restringir à loja da gôndola em edição.
 */
trait ResolvesGondolaStoreId
{
    use UsesPlannerateTenantDatabase;

    private function resolveGondolaStoreId(Gondola $gondola): ?string
    {
        $planogram = $gondola->loadMissing('planogram:id,store_id,cluster_id')->planogram;

        if (! $planogram) {
            return null;
        }

        if ($planogram->store_id !== null) {
            return (string) $planogram->store_id;
        }

        if ($planogram->cluster_id !== null) {
            $storeId = $this->plannerateTenantTable('clusters')
                ->where('id', $planogram->cluster_id)
                ->whereNull('deleted_at')
                ->value('store_id');

            return $storeId !== null ? (string) $storeId : null;
        }

        return null;
    }
}
