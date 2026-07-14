<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Tipos de mudança detectados entre o layout atual da gôndola e o layout proposto
 * pela reotimização. Um mesmo produto pode acumular vários (ex.: mudou de prateleira
 * E ganhou frentes), por isso o diff carrega uma lista, não um valor único.
 */
enum LayoutChangeType: string
{
    /** Produto não estava na gôndola e passa a estar. */
    case Added = 'added';

    /** Produto sai da gôndola (sem virar rejeitado explícito). */
    case Removed = 'removed';

    /** Mesmo produto, mais frentes. */
    case FacingsIncreased = 'facings_increased';

    /** Mesmo produto, menos frentes. */
    case FacingsDecreased = 'facings_decreased';

    /** Mudou de módulo e/ou prateleira. */
    case Moved = 'moved';

    /** Mudou o empilhamento vertical (unidades em altura). */
    case StackingChanged = 'stacking_changed';

    /** Passou a ser rejeitado (não coube / bloqueado / sem dimensão). */
    case RejectedAdded = 'rejected_added';

    /** Era rejeitado e agora entra na gôndola. */
    case RejectedResolved = 'rejected_resolved';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'Entrou',
            self::Removed => 'Saiu',
            self::FacingsIncreased => 'Ganhou frentes',
            self::FacingsDecreased => 'Perdeu frentes',
            self::Moved => 'Mudou de lugar',
            self::StackingChanged => 'Mudou empilhamento',
            self::RejectedAdded => 'Passou a rejeitado',
            self::RejectedResolved => 'Deixou de ser rejeitado',
        };
    }

    /** Mudanças que reduzem a presença do produto — destacadas em vermelho na UI. */
    public function isNegative(): bool
    {
        return in_array($this, [self::Removed, self::FacingsDecreased, self::RejectedAdded], true);
    }
}
