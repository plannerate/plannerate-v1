<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Natureza de uma execução de geração.
 *
 * Separar os dois é o que impede uma simulação de background (reotimização) de se passar
 * pela geração corrente da gôndola nas telas do editor.
 */
enum GenerationRunKind: string
{
    /** Escreve o resultado na gôndola. */
    case Apply = 'apply';

    /** Dry-run: calcula o layout para a proposta de reotimização, sem tocar na gôndola. */
    case Proposal = 'proposal';

    public function label(): string
    {
        return match ($this) {
            self::Apply => 'Geração',
            self::Proposal => 'Simulação',
        };
    }
}
