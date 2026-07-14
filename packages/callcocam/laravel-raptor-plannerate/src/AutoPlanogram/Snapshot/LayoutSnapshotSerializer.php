<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Serializa um layout (PlacedSegment[]) para JSON e de volta.
 *
 * É o que permite guardar uma proposta de reotimização e aplicá-la depois: o usuário aprova
 * um layout específico e é exatamente esse layout que vai para a gôndola — sem recalcular.
 *
 * PlacedSegment::toArray() NÃO serve aqui: ele emite layers_count (para relatório), não as
 * layers em si, e sem elas o layout não pode ser reconstruído.
 */
final class LayoutSnapshotSerializer
{
    /** Versão do formato. Snapshots antigos com versão desconhecida são recusados, não adivinhados. */
    public const VERSION = 1;

    /**
     * @param  Collection<int, PlacedSegment>  $segments
     * @return array{version: int, segments: list<array<string, mixed>>}
     */
    public function toArray(Collection $segments): array
    {
        return [
            'version' => self::VERSION,
            'segments' => $segments
                ->map(fn (PlacedSegment $segment): array => [
                    'section_id' => $segment->sectionId,
                    'shelf_id' => $segment->shelfId,
                    'ordering' => $segment->ordering,
                    'position' => $segment->position,
                    'width' => $segment->width,
                    'distributed_width' => $segment->distributedWidth,
                    'shelf_level' => $segment->shelfLevel?->value,
                    'layers' => $segment->layers
                        ->map(fn (PlacedLayer $layer): array => [
                            'product_id' => $layer->productId,
                            'ean' => $layer->ean,
                            'quantity' => $layer->quantity,
                            'height' => $layer->height,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{version?: int, segments?: list<array<string, mixed>>}  $snapshot
     * @return Collection<int, PlacedSegment>
     *
     * @throws RuntimeException quando a versão do formato é desconhecida.
     */
    public function fromArray(array $snapshot): Collection
    {
        $version = $snapshot['version'] ?? null;

        if ($version !== self::VERSION) {
            throw new RuntimeException(
                sprintf('Snapshot de layout com versão não suportada: %s (esperado %d).', var_export($version, true), self::VERSION)
            );
        }

        return collect($snapshot['segments'] ?? [])
            ->map(fn (array $segment): PlacedSegment => new PlacedSegment(
                sectionId: (string) $segment['section_id'],
                shelfId: (string) $segment['shelf_id'],
                ordering: (int) $segment['ordering'],
                position: (int) $segment['position'],
                width: (int) $segment['width'],
                distributedWidth: (int) $segment['distributed_width'],
                layers: collect($segment['layers'] ?? [])
                    ->map(fn (array $layer): PlacedLayer => new PlacedLayer(
                        productId: (string) $layer['product_id'],
                        ean: (string) ($layer['ean'] ?? ''),
                        quantity: (int) $layer['quantity'],
                        height: (int) $layer['height'],
                    ))
                    ->values(),
                shelfLevel: isset($segment['shelf_level'])
                    ? ShelfLevel::tryFrom((string) $segment['shelf_level'])
                    : null,
            ))
            ->values();
    }
}
