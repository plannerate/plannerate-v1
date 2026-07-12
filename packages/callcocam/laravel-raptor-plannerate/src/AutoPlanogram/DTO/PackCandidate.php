<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\ShelfKnapsackPacker;

/**
 * Um produto concorrendo por espaço numa prateleira, do ponto de vista do empacotador.
 *
 * O ShelfKnapsackPacker não conhece Produto, slot, ABC nem zona térmica: toda a regra de
 * negócio já foi traduzida aqui em número. O que sobra para ele é geometria e valor.
 *
 * @see ShelfKnapsackPacker
 */
final class PackCandidate
{
    public function __construct(
        /** Largura de UMA frente, em cm (exata, sem arredondar). */
        public readonly float $singleWidth,

        /** Frentes mínimas: se o produto entrar, entra com pelo menos isto. */
        public readonly int $minFacings,

        /** Frentes máximas: teto já consolidado (slot, estoque alvo, share por SKU). */
        public readonly int $maxFacings,

        /**
         * Valor de ESTAR na prateleira, em (0, 1].
         *
         * Domina o valor das frentes (ver INCLUSION_WEIGHT): variedade antes de profundidade —
         * o mesmo princípio que o overflow pass já aplica ("variedade < profundidade — errado").
         * Deriva do ranking, então entre dois produtos que disputam o mesmo vão vence o
         * mais bem ranqueado.
         */
        public readonly float $inclusionScore,

        /**
         * Peso de cada frente EXTRA, em (0, 1] — traduz o `facing_expansion` do slot
         * (score, estoque atual, déficit de estoque alvo ou igual para todos).
         */
        public readonly float $facingWeight,

        /**
         * Obrigatório: não pode ficar de fora (frentes >= minFacings).
         *
         * É o que garante a não-regressão. Todo produto que o first-fit atual já colocaria
         * entra como obrigatório, então a solução antiga é sempre viável no espaço de busca —
         * o empacotador só pode empatar ou melhorar, nunca perder um SKU.
         */
        public readonly bool $forced = false,
    ) {}
}
