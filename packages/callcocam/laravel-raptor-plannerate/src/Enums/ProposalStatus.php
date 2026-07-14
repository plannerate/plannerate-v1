<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Ciclo de vida de uma proposta de reotimização.
 *
 * `NoChanges` existe para não poluir a fila de aprovação: se o reprocessamento chegou ao mesmo
 * layout, não há nada para o usuário decidir — mas o registro fica, provando que a análise rodou.
 *
 * `Superseded` é o desfecho de uma proposta que envelheceu: a gôndola mudou depois que o diff foi
 * calculado (edição manual ou nova geração), então aprovar aplicaria um layout baseado numa
 * realidade que não existe mais.
 *
 * `Expired` é o desfecho de uma proposta que ninguém decidiu. Não é só higiene: o agendador não
 * analisa gôndola com proposta pendente (duas propostas partiriam do mesmo baseline), então uma
 * proposta ignorada TRAVA a reotimização daquela gôndola indefinidamente. Expirar destrava.
 */
enum ProposalStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case NoChanges = 'no_changes';
    case Superseded = 'superseded';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando revisão',
            self::Applied => 'Aplicada',
            self::Rejected => 'Rejeitada',
            self::NoChanges => 'Sem mudanças',
            self::Superseded => 'Desatualizada',
            self::Expired => 'Expirada',
            self::Failed => 'Falhou',
        };
    }

    /** Estados finais: a proposta não pode mais ser aprovada nem rejeitada. */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
