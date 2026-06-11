<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

enum SizeOrder: string
{
    case Asc = 'asc';
    case Desc = 'desc';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Asc => 'Do menor para o maior',
            self::Desc => 'Do maior para o menor',
            self::None => 'Sem ordenação por tamanho',
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
