<?php

namespace App\Enums;

enum PlacementFailureReason: string
{
    case HeightExceedsShelf = 'height_exceeds_shelf';
    case NoHorizontalSpace = 'no_horizontal_space';
    case NoShelfAtLevel = 'no_shelf_at_level';
    case MissingDimensions = 'missing_dimensions';

    public function label(): string
    {
        return match ($this) {
            self::HeightExceedsShelf => 'Produto mais alto que a prateleira',
            self::NoHorizontalSpace => 'Sem espaço horizontal disponível',
            self::NoShelfAtLevel => 'Nível de prateleira lotado',
            self::MissingDimensions => 'Produto sem dimensões cadastradas (width/height)',
        };
    }

    public function isPhysical(): bool
    {
        return $this === self::HeightExceedsShelf;
    }

    public function isDataQuality(): bool
    {
        return $this === self::MissingDimensions;
    }
}
