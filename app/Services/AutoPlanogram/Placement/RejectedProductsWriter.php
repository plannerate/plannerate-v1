<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Models\PlanogramRejectedProduct;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use Illuminate\Support\Str;

/**
 * Persiste produtos rejeitados na geração do planograma.
 * Limpa registros anteriores da gôndola antes de inserir os novos.
 */
final class RejectedProductsWriter
{
    public function write(string $planogramId, string $gondolaId, string $tenantId, PlanogramOutput $output): void
    {
        PlanogramRejectedProduct::where('gondola_id', $gondolaId)
            ->where('planogram_id', $planogramId)
            ->delete();

        if ($output->rejectedProducts->isEmpty()) {
            return;
        }

        $now = now();
        $slotAnalysisIndex = $this->buildSlotAnalysisIndex($output->slotAnalysis);

        // Agrupa por product_id para coletar todos os shelf_orders onde foi rejeitado
        $byProduct = $output->rejectedProducts
            ->filter(fn ($r) => $r['product'] !== null)
            ->groupBy(fn ($r) => $r['product']->id);

        $records = $byProduct
            ->map(function ($rejections) use ($planogramId, $gondolaId, $tenantId, $now, $slotAnalysisIndex): array {
                $first = $rejections->first();
                $product = $first['product'];
                $slotId = $first['slot_id'] ?? null;
                $slotData = $slotId ? ($slotAnalysisIndex[$slotId] ?? []) : [];

                $allShelfOrders = $rejections
                    ->map(fn ($r) => isset($r['slot_id']) ? ($slotAnalysisIndex[$r['slot_id']]['shelf_order'] ?? null) : null)
                    ->filter(fn ($v) => $v !== null)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                return [
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'planogram_id' => $planogramId,
                    'gondola_id' => $gondolaId,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'ean' => $product->ean ?? null,
                    'image_url' => $product->image_url ?? null,
                    'product_width' => $product->width ?? null,
                    'product_height' => $product->height ?? null,
                    'rejection_reason' => $first['reason']->value,
                    'slot_id' => $slotId,
                    'category_name' => $slotData['category_name'] ?? null,
                    'category_id' => $slotData['category_id'] ?? null,
                    'module_number' => $slotData['module_number'] ?? null,
                    'shelf_order' => $slotData['shelf_order'] ?? null,
                    'rejected_shelf_orders' => json_encode($allShelfOrders),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->toArray();

        if (empty($records)) {
            return;
        }

        PlanogramRejectedProduct::insert($records);
    }

    /** @param list<array<string, mixed>> $slotAnalysis */
    private function buildSlotAnalysisIndex(array $slotAnalysis): array
    {
        $index = [];

        foreach ($slotAnalysis as $slot) {
            if (isset($slot['slot_id'])) {
                $index[$slot['slot_id']] = $slot;
            }
        }

        return $index;
    }
}
