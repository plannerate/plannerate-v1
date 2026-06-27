<?php

namespace App\Enums;

/**
 * Tipo de divergência apontada durante a execução em loja.
 *
 * Caracteriza o problema encontrado pelo executor frente ao planograma
 * aprovado (ruptura, produto divergente, falta de espaço, etc.).
 */
enum ExecutionDivergenceType: string
{
    /** Produto em ruptura (sem estoque na loja). */
    case Ruptura = 'ruptura';

    /** Produto divergente do planejado. */
    case Divergent = 'divergente';

    /** Falta de espaço físico para o planejado. */
    case NoSpace = 'falta_espaco';

    /** Embalagem diferente da cadastrada. */
    case DifferentPackaging = 'embalagem_diferente';

    /** Produto não localizado na loja. */
    case NotFound = 'nao_localizado';

    /** Produto sem cadastro. */
    case NotRegistered = 'sem_cadastro';

    /** Quantidade insuficiente para a execução. */
    case InsufficientQuantity = 'quantidade_insuficiente';

    /** Outro tipo de divergência. */
    case Other = 'outro';
}
