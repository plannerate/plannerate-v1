<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

enum CategoryRole: string
{
    case Destino = 'destino';
    case Rotina = 'rotina';
    case Conveniencia = 'conveniencia';
    case Impulso = 'impulso';
    case Sazonal = 'sazonal';
    case Complementar = 'complementar';

    public function label(): string
    {
        return match ($this) {
            self::Destino => 'Destino',
            self::Rotina => 'Rotina',
            self::Conveniencia => 'Conveniência',
            self::Impulso => 'Impulso',
            self::Sazonal => 'Sazonal',
            self::Complementar => 'Complementar',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Destino => 'Gera tráfego, ocupa área nobre ou abre corredor',
            self::Rotina => 'Exposição equilibrada no centro da gôndola',
            self::Conveniencia => 'Leitura simples e acesso fácil',
            self::Impulso => 'Ocupa área quente ou de maior visibilidade',
            self::Sazonal => 'Recebe destaque temporário conforme sazonalidade',
            self::Complementar => 'Fica em zona fria ou área de associação',
        };
    }

    /** Zona física preferencial sugerida pelo papel. */
    public function suggestedZone(): string
    {
        return match ($this) {
            self::Destino, self::Impulso => 'quente',
            self::Rotina, self::Conveniencia => 'neutra',
            self::Sazonal => 'quente',
            self::Complementar => 'fria',
        };
    }
}
