<?php

namespace App\Enums;

enum PlacementFailureReason: string
{
    case HeightExceedsShelf = 'height_exceeds_shelf';
    case NoHorizontalSpace = 'no_horizontal_space';
    case NoShelfAtLevel = 'no_shelf_at_level';

    public function label(): string
    {
        return match ($this) {
            self::HeightExceedsShelf => 'Produto mais alto que a prateleira',
            self::NoHorizontalSpace => 'Sem espaço horizontal disponível',
            self::NoShelfAtLevel => 'Nível de prateleira lotado',
        };
    }

    public function isPhysical(): bool
    {
        return $this === self::HeightExceedsShelf;
    }
}
