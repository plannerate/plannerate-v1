<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Sentido de circulação do cliente na frente da gôndola.
 *
 * LeftToRight (padrão): cliente entra pela esquerda — início do fluxo = posição X=0.
 * RightToLeft: cliente entra pela direita — início do fluxo = posição X=máximo.
 * A inversão espelha as posições físicas dos produtos; a ordem lógica dos critérios é preservada.
 */
enum FlowDirection: string
{
    case LeftToRight = 'left_to_right';
    case RightToLeft = 'right_to_left';

    public function label(): string
    {
        return match ($this) {
            self::LeftToRight => 'Esquerda → Direita (padrão)',
            self::RightToLeft => 'Direita → Esquerda',
        };
    }

    /** Símbolo de seta para exibição compacta no frontend. */
    public function arrow(): string
    {
        return match ($this) {
            self::LeftToRight => '→',
            self::RightToLeft => '←',
        };
    }
}
