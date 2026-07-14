<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Reoptimization;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\LayoutDiff;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\LayoutDiffEntry;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\Enums\LayoutChangeType;
use Illuminate\Support\Collection;

/**
 * Compara o layout ATUAL da gôndola com o layout PROPOSTO pela reotimização e descreve,
 * por produto, o que mudaria — a informação que o usuário revisa antes de aprovar.
 *
 * Agrega por produto (não por segmento): o gestor pensa em "este item ganhou 2 frentes e
 * subiu uma prateleira", não em segmentos. A posição reportada é a do segmento de MAIOR
 * facing do produto (onde ele está de fato "presente"), quando ocupa mais de um lugar.
 */
final class GondolaLayoutDiffService
{
    /**
     * @param  Collection<int, PlacedSegment>  $baseline  Layout atual.
     * @param  Collection<int, PlacedSegment>  $proposed  Layout proposto.
     * @param  list<array<string, mixed>>  $rejectedBefore  Rejeitados atuais (linhas com product_id).
     * @param  list<array<string, mixed>>  $rejectedAfter  Rejeitados propostos.
     * @param  LayoutDiffContext  $context  Metadados (posição da prateleira, dados do produto).
     */
    public function diff(
        Collection $baseline,
        Collection $proposed,
        array $rejectedBefore,
        array $rejectedAfter,
        LayoutDiffContext $context,
    ): LayoutDiff {
        $before = $this->aggregateByProduct($baseline, $context);
        $after = $this->aggregateByProduct($proposed, $context);

        $rejectedBeforeIds = $this->indexRejected($rejectedBefore);
        $rejectedAfterIds = $this->indexRejected($rejectedAfter);

        $productIds = collect(array_keys($before))
            ->merge(array_keys($after))
            ->merge(array_keys($rejectedBeforeIds))
            ->merge(array_keys($rejectedAfterIds))
            ->unique()
            ->values();

        $entries = [];
        $unchanged = 0;

        foreach ($productIds as $productId) {
            $wasPlaced = isset($before[$productId]);
            $isPlaced = isset($after[$productId]);
            $wasRejected = isset($rejectedBeforeIds[$productId]);
            $isRejected = isset($rejectedAfterIds[$productId]);

            $changes = $this->detectChanges(
                $before[$productId] ?? null,
                $after[$productId] ?? null,
                $wasRejected,
                $isRejected,
            );

            if ($changes === []) {
                $unchanged++;

                continue;
            }

            $entries[] = new LayoutDiffEntry(
                productId: $productId,
                productName: $context->productName($productId),
                ean: $context->productEan($productId),
                imageUrl: $context->productImageUrl($productId),
                changes: $changes,
                facingsBefore: $wasPlaced ? $before[$productId]['facings'] : null,
                facingsAfter: $isPlaced ? $after[$productId]['facings'] : null,
                positionBefore: $wasPlaced ? $before[$productId]['position'] : null,
                positionAfter: $isPlaced ? $after[$productId]['position'] : null,
                rejectionReason: $isRejected ? ($rejectedAfterIds[$productId] ?? null) : null,
            );
        }

        // Ordena por relevância: o que sai/entra primeiro, depois variação de frentes, depois o resto.
        usort($entries, fn (LayoutDiffEntry $a, LayoutDiffEntry $b): int => $this->weight($b) <=> $this->weight($a));

        return new LayoutDiff(
            entries: $entries,
            summary: $this->summarize($entries, $unchanged, count($before), count($after)),
            hasChanges: $entries !== [],
        );
    }

    /**
     * @param  ?array{facings: int, height: int, position: array{module: int, shelf: int}}  $before
     * @param  ?array{facings: int, height: int, position: array{module: int, shelf: int}}  $after
     * @return list<LayoutChangeType>
     */
    private function detectChanges(?array $before, ?array $after, bool $wasRejected, bool $isRejected): array
    {
        $changes = [];

        // Rejeição é ortogonal ao posicionamento: um produto pode estar posicionado e, na
        // proposta, virar rejeitado (não coube mais) — as duas coisas são reportadas.
        if (! $wasRejected && $isRejected) {
            $changes[] = LayoutChangeType::RejectedAdded;
        }

        if ($wasRejected && ! $isRejected && $after !== null) {
            $changes[] = LayoutChangeType::RejectedResolved;
        }

        if ($before === null && $after !== null) {
            // "Entrou" só é novidade se não veio de um rejeitado resolvido (já reportado acima).
            if (! $wasRejected) {
                $changes[] = LayoutChangeType::Added;
            }

            return $changes;
        }

        if ($before !== null && $after === null) {
            // "Saiu" só é novidade se não virou rejeitado (já reportado acima).
            if (! $isRejected) {
                $changes[] = LayoutChangeType::Removed;
            }

            return $changes;
        }

        if ($before === null || $after === null) {
            return $changes;
        }

        if ($after['facings'] > $before['facings']) {
            $changes[] = LayoutChangeType::FacingsIncreased;
        } elseif ($after['facings'] < $before['facings']) {
            $changes[] = LayoutChangeType::FacingsDecreased;
        }

        if ($after['position'] !== $before['position']) {
            $changes[] = LayoutChangeType::Moved;
        }

        if ($after['height'] !== $before['height']) {
            $changes[] = LayoutChangeType::StackingChanged;
        }

        return $changes;
    }

    /**
     * Soma frentes por produto e resolve a posição principal (a do segmento de maior facing).
     *
     * @param  Collection<int, PlacedSegment>  $segments
     * @return array<string, array{facings: int, height: int, position: array{module: int, shelf: int}}>
     */
    private function aggregateByProduct(Collection $segments, LayoutDiffContext $context): array
    {
        $byProduct = [];

        foreach ($segments as $segment) {
            $position = $context->positionOf($segment->shelfId);

            foreach ($segment->layers as $layer) {
                $id = $layer->productId;

                if (! isset($byProduct[$id])) {
                    $byProduct[$id] = [
                        'facings' => 0,
                        'height' => $layer->height,
                        'position' => $position,
                        'primary_facings' => 0,
                    ];
                }

                $byProduct[$id]['facings'] += $layer->quantity;

                // A posição "do produto" é a do segmento onde ele tem mais frentes.
                if ($layer->quantity > $byProduct[$id]['primary_facings']) {
                    $byProduct[$id]['primary_facings'] = $layer->quantity;
                    $byProduct[$id]['position'] = $position;
                    $byProduct[$id]['height'] = $layer->height;
                }
            }
        }

        foreach ($byProduct as $id => $data) {
            unset($byProduct[$id]['primary_facings']);
        }

        return $byProduct;
    }

    /**
     * @param  list<array<string, mixed>>  $rejected
     * @return array<string, ?string> product_id => rejection_reason
     */
    private function indexRejected(array $rejected): array
    {
        $index = [];

        foreach ($rejected as $row) {
            $productId = $row['product_id'] ?? null;

            if ($productId !== null) {
                $index[(string) $productId] = isset($row['rejection_reason']) ? (string) $row['rejection_reason'] : null;
            }
        }

        return $index;
    }

    /** Peso para ordenação: mudanças mais "drásticas" aparecem primeiro. */
    private function weight(LayoutDiffEntry $entry): int
    {
        $weights = [
            LayoutChangeType::Removed->value => 100,
            LayoutChangeType::RejectedAdded->value => 90,
            LayoutChangeType::Added->value => 80,
            LayoutChangeType::RejectedResolved->value => 70,
            LayoutChangeType::FacingsDecreased->value => 60,
            LayoutChangeType::FacingsIncreased->value => 50,
            LayoutChangeType::Moved->value => 20,
            LayoutChangeType::StackingChanged->value => 10,
        ];

        return collect($entry->changes)
            ->map(fn (LayoutChangeType $c): int => $weights[$c->value] ?? 0)
            ->max() ?? 0;
    }

    /**
     * @param  list<LayoutDiffEntry>  $entries
     * @return array<string, int>
     */
    private function summarize(array $entries, int $unchanged, int $productsBefore, int $productsAfter): array
    {
        $summary = [];

        foreach (LayoutChangeType::cases() as $type) {
            $summary[$type->value] = 0;
        }

        foreach ($entries as $entry) {
            foreach ($entry->changes as $change) {
                $summary[$change->value]++;
            }
        }

        $summary['unchanged'] = $unchanged;
        $summary['total_changed'] = count($entries);
        $summary['products_before'] = $productsBefore;
        $summary['products_after'] = $productsAfter;

        return $summary;
    }
}
