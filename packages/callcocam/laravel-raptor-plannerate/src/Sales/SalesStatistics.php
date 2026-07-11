<?php

namespace Callcocam\LaravelRaptorPlannerate\Sales;

/**
 * Fonte única dos cálculos ESTATÍSTICOS de vendas usados pelas análises
 * (ABC, Paper/BCG e Estoque-Alvo).
 *
 * Antes, estas fórmulas viviam embutidas e sem testes dentro de cada service de
 * análise. Centralizá-las aqui como funções puras dá um único lar para a
 * matemática de vendas e — pela primeira vez — cobertura de testes determinística.
 * As fórmulas foram portadas fielmente; o comportamento numérico é idêntico.
 */
class SalesStatistics
{
    /**
     * Média ponderada de uma linha de vendas (usada na curva ABC).
     *
     * Cada métrica só entra na soma de pesos quando o seu valor é diferente de
     * zero — assim um produto sem margem (ou sem quantidade) não é penalizado por
     * um peso cujo numerador é nulo. Retorna 0 quando nenhuma métrica contribui.
     */
    public static function weightedAverage(
        float $qtde,
        float $valor,
        float $margem,
        float $pesoQtde,
        float $pesoValor,
        float $pesoMargem,
    ): float {
        $somaPesos = 0.0;
        $mediaPonderada = 0.0;

        if ($qtde != 0.0) {
            $somaPesos += $pesoQtde;
            $mediaPonderada += $qtde * $pesoQtde;
        }

        if ($valor != 0.0) {
            $somaPesos += $pesoValor;
            $mediaPonderada += $valor * $pesoValor;
        }

        if ($margem != 0.0) {
            $somaPesos += $pesoMargem;
            $mediaPonderada += $margem * $pesoMargem;
        }

        return $somaPesos != 0.0 ? $mediaPonderada / $somaPesos : 0.0;
    }

    /**
     * Participação (%) do valor do produto no total da categoria (market share).
     * Retorna 0 quando a categoria não tem valor no período.
     */
    public static function marketShare(float $value, float $categoryTotal): float
    {
        return $categoryTotal > 0 ? ($value / $categoryTotal) * 100 : 0.0;
    }

    /**
     * Taxa de crescimento (%) entre o período atual e o anterior.
     * Retorna null quando não há base de comparação (período anterior <= 0),
     * sinalizando produto novo/sem histórico — tratado à parte pelo chamador.
     */
    public static function growthRate(float $current, float $previous): ?float
    {
        return $previous > 0
            ? round((($current - $previous) / $previous) * 100, 4)
            : null;
    }

    /**
     * Mediana de uma sequência de números. Retorna null para sequência vazia.
     *
     * @param  iterable<int|string, int|float>  $values
     */
    public static function median(iterable $values): ?float
    {
        $sorted = array_values(array_map('floatval', is_array($values) ? $values : iterator_to_array($values)));
        sort($sorted);
        $count = count($sorted);

        if ($count === 0) {
            return null;
        }

        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? ($sorted[$mid - 1] + $sorted[$mid]) / 2
            : $sorted[$mid];
    }

    /**
     * Média aritmética de uma sequência de números. Retorna null para sequência vazia.
     *
     * Usada pela Análise BCG como método de corte alternativo à mediana, para
     * reproduzir o comportamento da planilha VBA original. A mediana é o padrão
     * porque vendas de varejo têm cauda longa e a média é puxada pelos líderes.
     *
     * @param  iterable<int|string, int|float>  $values
     */
    public static function mean(iterable $values): ?float
    {
        $items = array_map('floatval', is_array($values) ? $values : iterator_to_array($values));

        if (count($items) === 0) {
            return null;
        }

        return array_sum($items) / count($items);
    }

    /**
     * Posição percentual (0-100) de um valor dentro de uma população — a fração de
     * itens menores ou iguais a ele. Retorna 0 para população vazia.
     *
     * É o score CONTÍNUO que acompanha o quadrante discreto da Análise BCG: o rótulo
     * do quadrante esconde a distância até o limiar, e classificação por corte é
     * notoriamente instável entre períodos. O percentil preserva essa informação.
     *
     * @param  iterable<int|string, int|float>  $values
     */
    public static function percentileRank(float $value, iterable $values): float
    {
        $items = array_map('floatval', is_array($values) ? $values : iterator_to_array($values));
        $count = count($items);

        if ($count === 0) {
            return 0.0;
        }

        $atOrBelow = count(array_filter($items, fn (float $item) => $item <= $value));

        return ($atOrBelow / $count) * 100;
    }

    /**
     * Amplitude (máximo − mínimo) de uma sequência. Retorna 0 para sequência vazia.
     *
     * Serve de escala para decidir se um item está "em cima da linha" de corte:
     * a proximidade do limiar só é interpretável em relação à dispersão do grupo.
     *
     * @param  iterable<int|string, int|float>  $values
     */
    public static function range(iterable $values): float
    {
        $items = array_map('floatval', is_array($values) ? $values : iterator_to_array($values));

        if (count($items) === 0) {
            return 0.0;
        }

        return max($items) - min($items);
    }

    /**
     * Z-score (inverso da distribuição normal padrão) — equivalente ao NormSInv
     * do Excel/VBA. Usa valores exatos tabelados para os níveis de serviço comuns
     * e a aproximação de Abramowitz e Stegun para os demais. Retorna 0 fora de (0,1).
     *
     * @param  float  $probability  Probabilidade / nível de serviço (0.5 a 0.999...)
     */
    public static function zScore(float $probability): float
    {
        if ($probability <= 0 || $probability >= 1) {
            return 0.0;
        }

        // Valores exatos para casos comuns.
        // IMPORTANTE: as chaves são STRING — chaves float em arrays PHP são truncadas
        // para int (0.70, 0.80... viram todas 0), o que colapsaria a tabela.
        $commonValues = [
            '0.70' => 0.5244,
            '0.75' => 0.6745,
            '0.80' => 0.8416,
            '0.85' => 1.0364,
            '0.90' => 1.2816,
            '0.95' => 1.6449,
            '0.99' => 2.3263,
        ];

        $key = number_format($probability, 2, '.', '');

        if (isset($commonValues[$key])) {
            return $commonValues[$key];
        }

        // Aproximação de Abramowitz e Stegun para os demais valores.
        $t = sqrt(-2.0 * log(1.0 - $probability));
        $c0 = 2.515517;
        $c1 = 0.802853;
        $c2 = 0.010328;
        $d1 = 1.432788;
        $d2 = 0.189269;
        $d3 = 0.001308;

        return $t - ($c0 + $c1 * $t + $c2 * $t * $t) / (1.0 + $d1 * $t + $d2 * $t * $t + $d3 * $t * $t * $t);
    }

    /**
     * Variabilidade da demanda = desvio padrão ÷ média (coeficiente de variação).
     * Retorna 0 quando a média é zero.
     */
    public static function variability(float $media, float $desvioPadrao): float
    {
        return $media > 0 ? $desvioPadrao / $media : 0.0;
    }

    /**
     * Estoque de segurança = Z-score × desvio padrão (arredondado para unidade).
     * Assume variabilidade de 1 período (não multiplica por sqrt da cobertura).
     */
    public static function safetyStock(float $zScore, float $desvioPadrao): float
    {
        return round($zScore * $desvioPadrao, 0);
    }

    /**
     * Estoque mínimo = demanda média × dias de cobertura (arredondado para unidade).
     */
    public static function minimumStock(float $media, int $coberturaDias): float
    {
        return round($media * $coberturaDias, 0);
    }

    /**
     * Estoque-alvo = estoque mínimo + estoque de segurança (arredondado para unidade).
     */
    public static function targetStock(float $minimumStock, float $safetyStock): float
    {
        return round($minimumStock + $safetyStock, 0);
    }
}
