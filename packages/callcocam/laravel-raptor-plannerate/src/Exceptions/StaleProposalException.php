<?php

namespace Callcocam\LaravelRaptorPlannerate\Exceptions;

use RuntimeException;

/**
 * A gôndola mudou depois que a proposta foi calculada.
 *
 * Aplicar assim mesmo sobrescreveria o trabalho feito no meio-tempo (uma edição manual, uma
 * geração nova) com um layout construído sobre um estado que não existe mais — e o diff que o
 * usuário aprovou descreveria mudanças em relação a um "antes" errado. É recusa, não aviso.
 */
class StaleProposalException extends RuntimeException {}
