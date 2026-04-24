<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

enum GondolaWorkflowStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::InProgress => 'Em Andamento',
            self::Completed => 'Concluído',
            self::Blocked => 'Bloqueado',
            self::Skipped => 'Ignorado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::Blocked => 'red',
            self::Skipped => 'yellow',
        };
    }
}
