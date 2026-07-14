<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Enums\LayoutChangeType;

/**
 * Uma linha do diff da reotimização: o que muda para UM produto.
 *
 * Agregado por produto (não por segmento): um produto pode ocupar vários segmentos e até
 * várias prateleiras, mas o usuário raciocina em "este produto ganhou 2 frentes e desceu
 * uma prateleira", não em termos de segmentos.
 *
 * @phpstan-type Position array{module: int, shelf: int}
 */
final readonly class LayoutDiffEntry
{
    /**
     * @param  list<LayoutChangeType>  $changes  Um produto pode acumular várias mudanças.
     * @param  ?int  $facingsBefore  null = não estava na gôndola.
     * @param  ?int  $facingsAfter  null = sai da gôndola.
     * @param  ?Position  $positionBefore  Posição do segmento de maior facing.
     * @param  ?Position  $positionAfter
     * @param  ?string  $rejectionReason  Motivo, quando passa a rejeitado (PlacementFailureReason).
     */
    public function __construct(
        public string $productId,
        public string $productName,
        public ?string $ean,
        public ?string $imageUrl,
        public array $changes,
        public ?int $facingsBefore,
        public ?int $facingsAfter,
        public ?array $positionBefore,
        public ?array $positionAfter,
        public ?string $rejectionReason = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'ean' => $this->ean,
            'image_url' => $this->imageUrl,
            'changes' => array_map(fn (LayoutChangeType $c): string => $c->value, $this->changes),
            'facings_before' => $this->facingsBefore,
            'facings_after' => $this->facingsAfter,
            'position_before' => $this->positionBefore,
            'position_after' => $this->positionAfter,
            'rejection_reason' => $this->rejectionReason,
        ];
    }
}
