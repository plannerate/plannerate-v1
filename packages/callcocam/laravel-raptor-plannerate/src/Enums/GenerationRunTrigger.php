<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Origem de uma execução de geração: pedida por alguém ou disparada pelo agendador.
 */
enum GenerationRunTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Scheduled => 'Agendada',
        };
    }
}
