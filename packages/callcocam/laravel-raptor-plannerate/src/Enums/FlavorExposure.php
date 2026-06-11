<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

enum FlavorExposure: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Vertical => 'Vertical (sabores em coluna)',
            self::Horizontal => 'Horizontal (sabores em linha)',
            self::Mixed => 'Misto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Vertical => 'orange',
            self::Horizontal => 'yellow',
            self::Mixed => 'gray',
        };
    }
}
