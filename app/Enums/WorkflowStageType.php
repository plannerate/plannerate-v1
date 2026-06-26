<?php

namespace App\Enums;

/**
 * Tipo de etapa do workflow.
 *
 * Distingue as etapas que compõem o fluxo "normal" do planograma (Flow,
 * que pode encerrar o fluxo na última delas) da etapa de Revisão Periódica
 * (PeriodicReview), que é pós-conclusão e disparada automaticamente quando
 * vence o período de análise do planograma.
 */
enum WorkflowStageType: string
{
    /** Etapa do fluxo normal (criação, revisões, aprovações, execução). */
    case Flow = 'flow';

    /** Etapa pós-conclusão, promovida automaticamente pelo scheduler. */
    case PeriodicReview = 'periodic_review';

    /**
     * Indica se esta etapa é a de Revisão Periódica (pós-conclusão).
     */
    public function isPeriodicReview(): bool
    {
        return $this === self::PeriodicReview;
    }
}
