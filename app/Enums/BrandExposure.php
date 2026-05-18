<?php

namespace App\Enums;

enum BrandExposure: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Vertical => 'Vertical (marcas em coluna)',
            self::Horizontal => 'Horizontal (marcas em linha)',
            self::Mixed => 'Misto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Vertical => 'purple',
            self::Horizontal => 'blue',
            self::Mixed => 'gray',
        };
    }
}
