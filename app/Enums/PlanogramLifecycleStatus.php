<?php

namespace App\Enums;

/**
 * Estado do ciclo de vida de fluxo de um planograma.
 *
 * Independente do `status` de publicação (draft|published): rastreia onde o
 * planograma está no processo de execução/revisão.
 * - InProgress: fluxo em andamento (gôndolas ainda percorrendo as etapas).
 * - Completed: todas as gôndolas não puladas concluíram a etapa final de fluxo.
 * - PeriodicReview: promovido automaticamente para Revisão Periódica.
 */
enum PlanogramLifecycleStatus: string
{
    /** Fluxo em andamento — gôndolas ainda nas etapas de fluxo. */
    case InProgress = 'in_progress';

    /** Fluxo concluído na etapa final (Execução Loja). */
    case Completed = 'completed';

    /** Em Revisão Periódica (pós-conclusão automática). */
    case PeriodicReview = 'periodic_review';
}
