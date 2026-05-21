<?php

namespace App\Enums;

enum ProductRuleType: string
{
    case Mandatory = 'mandatory';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Mandatory => 'Obrigatório',
            self::Blocked => 'Bloqueado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Mandatory => 'Entra no planograma mesmo sem histórico de vendas (produto novo, contrato, marca própria).',
            self::Blocked => 'Nunca entra no planograma (descontinuado, sem negociação, suspenso).',
        };
    }
}
