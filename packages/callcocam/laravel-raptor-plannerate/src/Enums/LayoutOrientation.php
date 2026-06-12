<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Disposição dos produtos dentro dos módulos da gôndola.
 *
 * Horizontal (padrão/legado): cada prateleira distribui seus produtos de forma
 * independente, da esquerda para a direita.
 *
 * Vertical (blocagem por marca): quando uma categoria ocupa várias prateleiras
 * do mesmo módulo, cada marca forma uma COLUNA alinhada — mesma faixa de X em
 * todas as prateleiras (ex.: gôndola de açúcar com uma coluna por marca).
 * A prateleira do chão pode ficar fora da blocagem (fardos/embalagens grandes).
 */
enum LayoutOrientation: string
{
    case Horizontal = 'horizontal';
    case Vertical = 'vertical';

    public function label(): string
    {
        return match ($this) {
            self::Horizontal => 'Horizontal (padrão)',
            self::Vertical => 'Vertical (blocagem por marca)',
        };
    }
}
