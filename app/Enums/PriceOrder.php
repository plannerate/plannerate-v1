<?php

namespace App\Enums;

enum PriceOrder: string
{
    case Asc = 'asc';
    case Desc = 'desc';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Asc => 'Do mais barato para o mais caro',
            self::Desc => 'Do mais caro para o mais barato',
            self::None => 'Sem ordenação por preço',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Asc => 'green',
            self::Desc => 'blue',
            self::None => 'gray',
        };
    }
}
