<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

/**
 * Aritmética de encaixe na prateleira — fonte única da precisão do empacotamento.
 *
 * O motor calculava larguras arredondando cada produto para centímetro inteiro. Isso
 * causava dois defeitos:
 *  1. produtos que cabiam por fração de cm eram rejeitados (ou entravam onde não cabiam,
 *     quando a largura unitária era arredondada ANTES de multiplicar pelas frentes);
 *  2. o erro de arredondamento se acumulava ao longo da prateleira (até 0,5cm por
 *     segmento), deixando sobra fantasma e fazendo os segmentos "andarem".
 *
 * Aqui a conta roda em float exato (cm com decimais) e o arredondamento acontece só na
 * hora de persistir — ver segmentBounds().
 */
final class PlacementMath
{
    /**
     * Tolerância para comparar larguras em cm.
     *
     * Sem ela, um produto que ocupa exatamente a largura restante poderia ser rejeitado
     * por erro de representação de ponto flutuante (ex.: 0.1 + 0.2 > 0.3).
     * 1e-6 cm = 10 nanômetros: fisicamente irrelevante, suficiente para o erro do float.
     */
    public const WIDTH_EPSILON_CM = 1e-6;

    /**
     * O produto cabe no espaço restante da prateleira?
     */
    public static function fits(float $occupied, float $width, float $available): bool
    {
        return $occupied + $width <= $available + self::WIDTH_EPSILON_CM;
    }

    /**
     * Folga física (cm) entre produtos vizinhos na mesma prateleira.
     *
     * Na loja existe uma pequena folga entre facings para o repositor conseguir tirar e
     * repor a peça; empacotar tudo encostado promete mais produto do que a prateleira
     * comporta de verdade. Configurável em `plannerate.auto_planogram.placement`.
     * Default 0.0 = comportamento legado (encostado).
     */
    public static function productSpacingCm(): float
    {
        return (float) config('plannerate.auto_planogram.placement.product_spacing_cm', 0.0);
    }

    /**
     * Folga a cobrar ANTES de colocar o próximo produto.
     *
     * Zero na primeira posição da prateleira (a folga é entre produtos, não uma margem
     * inicial) e zero quando o espaçamento está desligado.
     */
    public static function gapBefore(float $occupied, float $spacing): float
    {
        return $occupied > self::WIDTH_EPSILON_CM ? $spacing : 0.0;
    }

    /**
     * Converte uma posição exata (float, cm) num par [posição, largura] em cm INTEIROS,
     * do jeito que as colunas `segments.position` / `segments.width` exigem.
     *
     * Arredonda os PONTOS (início e fim), não a largura isolada: assim os segmentos ficam
     * contíguos por construção — o fim de um é exatamente o começo do próximo, sem gap
     * nem sobreposição — e o erro não se acumula ao longo da prateleira.
     *
     * @return array{0: int, 1: int} [posição em cm, largura em cm]
     */
    public static function segmentBounds(float $startX, float $exactWidth): array
    {
        $startCm = (int) round($startX);
        $endCm = (int) round($startX + $exactWidth);

        // Largura mínima 1cm: um produto com largura cadastrada abaixo de 0,5cm viraria um
        // segmento de largura zero (invisível no editor). Prefere-se 1cm visível a um bug mudo.
        return [$startCm, max(1, $endCm - $startCm)];
    }
}
