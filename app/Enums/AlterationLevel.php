<?php

namespace App\Enums;

/**
 * Nível mínimo de alteração necessário ao mudar configuração de um slot.
 * Mapeia o custo computacional crescente: reorder < redistribute < regenerate.
 */
enum AlterationLevel: string
{
    /** Apenas reposiciona segmentos já existentes (muda ordering/position). Produtos e frentes intactos. */
    case Reorder = 'reorder';

    /** Reagrupa segmentos por exposição (marca/sabor) mantendo {produto: frentes}. Posições recalculadas. */
    case Redistribute = 'redistribute';

    /** Regeneração total — muda produtos elegíveis, frentes, rejeitados, ocupação. */
    case Regenerate = 'regenerate';

    public function label(): string
    {
        return match ($this) {
            self::Reorder => 'Reordenando…',
            self::Redistribute => 'Redistribuindo…',
            self::Regenerate => 'Regerando planograma…',
        };
    }
}
