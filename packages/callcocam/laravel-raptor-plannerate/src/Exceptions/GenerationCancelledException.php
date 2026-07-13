<?php

namespace Callcocam\LaravelRaptorPlannerate\Exceptions;

use RuntimeException;

/**
 * A geração foi cancelada por uma regra de NEGÓCIO — não por uma falha técnica.
 *
 * Ex.: nenhum produto elegível na categoria escolhida. O usuário precisa ver o motivo, mas
 * não é um erro do sistema: não faz sentido tentar de novo nem alertar quem opera.
 *
 * ── Por que esta classe existe ───────────────────────────────────────────────────────────
 * O job capturava `\RuntimeException` para tratar esses cancelamentos. Só que `QueryException`
 * TAMBÉM é uma `RuntimeException` (via PDOException) — então uma falha real de banco era
 * engolida e reportada ao usuário como "geração cancelada", com o run marcado como falho mas
 * sem nada indicando que havia um defeito técnico. Foi o que aconteceu com a tabela ausente
 * `product_analyses`: o erro de verdade só apareceu quando alguém foi ler o log.
 *
 * Capturando este tipo específico, erro técnico volta a estourar — e o `failed()` do job o
 * trata como a falha que ele é.
 */
final class GenerationCancelledException extends RuntimeException {}
