<?php

namespace App\Enums;

enum SpaceFallback: string
{
    case ReduceC = 'reduce_c';
    case ReduceFacings = 'reduce_facings';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::ReduceC => 'Reduzir SKUs curva C primeiro',
            self::ReduceFacings => 'Reduzir facings para 1',
            self::Skip => 'Deixar incompleto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ReduceC => 'red',
            self::ReduceFacings => 'yellow',
            self::Skip => 'gray',
        };
    }
}
