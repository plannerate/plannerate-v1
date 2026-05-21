<?php

namespace App\Enums;

enum PlacementFailureReason: string
{
    case HeightExceedsShelf = 'height_exceeds_shelf';
    case NoHorizontalSpace = 'no_horizontal_space';
    case NoShelfAtLevel = 'no_shelf_at_level';
    case MissingDimensions = 'missing_dimensions';
    /** Produto ou marca/subcategoria explicitamente bloqueada por regra */
    case Blocked = 'blocked';
    /** Produto obrigatório que não coube no slot nem com 1 frente */
    case MandatoryNoSpace = 'mandatory_no_space';

    public function label(): string
    {
        return match ($this) {
            self::HeightExceedsShelf => 'Produto mais alto que a prateleira',
            self::NoHorizontalSpace => 'Sem espaço horizontal disponível',
            self::NoShelfAtLevel => 'Nível de prateleira lotado',
            self::MissingDimensions => 'Produto sem dimensões cadastradas (width/height)',
            self::Blocked => 'Produto bloqueado por regra',
            self::MandatoryNoSpace => 'Produto obrigatório sem espaço disponível',
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

    public function isHardRule(): bool
    {
        return $this === self::Blocked || $this === self::MandatoryNoSpace;
    }
}
