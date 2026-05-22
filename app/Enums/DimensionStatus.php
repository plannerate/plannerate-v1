<?php

namespace App\Enums;

enum DimensionStatus: string
{
    case Pending = 'pending';
    case Researching = 'researching';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case NotFound = 'not_found';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando pesquisa',
            self::Researching => 'Pesquisando…',
            self::AwaitingApproval => 'Aguardando aprovação',
            self::Approved => 'Aprovado',
            self::NotFound => 'Não encontrado',
            self::Rejected => 'Rejeitado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Researching => 'blue',
            self::AwaitingApproval => 'yellow',
            self::Approved => 'green',
            self::NotFound => 'orange',
            self::Rejected => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::NotFound, self::Rejected], true);
    }
}
