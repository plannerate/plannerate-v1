<?php

namespace App\Enums;

/**
 * Estado de tratativa de uma divergência da execução em loja.
 *
 * `Open` e `InAnalysis` são estados pendentes que bloqueiam a conclusão da
 * execução; `Justified`, `Resolved` e `Rejected` liberam a conclusão.
 */
enum ExecutionDivergenceStatus: string
{
    /** Aberta — registrada e ainda não tratada. */
    case Open = 'aberta';

    /** Justificada — possui justificativa que libera a conclusão. */
    case Justified = 'justificada';

    /** Em análise — sob avaliação; ainda pendente. */
    case InAnalysis = 'em_analise';

    /** Resolvida — tratada e encerrada. */
    case Resolved = 'resolvida';

    /** Rejeitada — descartada após análise. */
    case Rejected = 'rejeitada';

    /**
     * Indica se este estado ainda bloqueia a conclusão da execução.
     */
    public function blocksCompletion(): bool
    {
        return $this === self::Open || $this === self::InAnalysis;
    }
}
