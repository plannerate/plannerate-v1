<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PackCandidate;
use Illuminate\Support\Facades\Log;

/**
 * Empacotador exato de uma prateleira (bounded knapsack por programação dinâmica).
 *
 * ── O problema que ele resolve ────────────────────────────────────────────────────────
 * O motor antigo decidia a prateleira em duas passadas gulosas independentes:
 *   1. varria os candidatos ranqueados UMA vez e colocava cada um com a frente mínima se
 *      coubesse na hora — quem não coubesse era rejeitado e nunca mais reconsiderado;
 *   2. distribuía o vão restante em frentes extras num round-robin entre os já colocados.
 *
 * As duas passadas deixam sobra pela mesma razão: nenhuma delas enxerga a prateleira como
 * um todo. O round-robin para assim que o vão fica menor que o produto mais estreito da
 * prateleira, e o vão que sobra não pode ser preenchido por um rejeitado — ele já saiu do
 * jogo. É esse vão órfão que faz a gôndola "não fechar".
 *
 * Aqui a prateleira é resolvida de uma vez só: as frentes de cada produto são a variável
 * livre dentro de [min, max] e os rejeitados por espaço voltam a concorrer. O DP procura a
 * combinação de maior valor que cabe na largura — inclusive combinações que o guloso jamais
 * encontraria (dar uma frente a menos para o produto A porque isso faz o produto B caber).
 *
 * ── Por que não perde nada do que o motor antigo fazia ────────────────────────────────
 * Todo produto que o first-fit já colocaria entra como `forced`. A solução antiga é, portanto,
 * sempre viável no espaço de busca do DP — e como o DP devolve a de MAIOR valor, o resultado
 * empata ou melhora, nunca perde um SKU que o algoritmo antigo colocaria.
 *
 * ── Modelo de valor: três prioridades, nesta ordem ───────────────────────────────────
 *   valor(i, f) = INCLUSION_WEIGHT · inclusionScore(i)      ① variedade  — estar na prateleira
 *               + FILL_WEIGHT      · larguraOcupada(i, f)   ② ocupação   — FECHAR a gôndola
 *               + facingWeight(i)  · H(f)                   ③ profundidade — de quem são as frentes
 *
 * ① domina tudo: perder um SKU custa ≥ 5000, e o máximo que se ganha com toda a ocupação e
 *   todas as frentes juntas é ~690. Nenhuma combinação de espaço compensa deixar um SKU fora.
 *
 * ② existe porque "fechar a gôndola" é o objetivo do projeto, não um efeito colateral. Sem
 *   este termo o DP maximiza só o valor comercial — e chega a PREFERIR deixar 1cm vazio se
 *   isso concentrar mais frentes no produto melhor ranqueado. Com ele, entre duas soluções o
 *   DP escolhe a que ocupa mais centímetros, e usa ③ só para decidir de QUEM são as frentes.
 *
 * ③ é a série harmônica: frentes têm retorno decrescente (a 5ª frente vende bem menos que a
 *   2ª) — a literatura de SSAP confirma, e é o que impede o DP de despejar a prateleira
 *   inteira no produto de maior peso.
 *
 * Referência: docs/gondola-precisao-automatica/ (Fase 2 do plano).
 */
final class ShelfKnapsackPacker
{
    /**
     * Acima disto o DP é abortado e o guloso segue valendo.
     *
     * Não é um limite de qualidade e sim de custo: o DP é O(candidatos × largura × frentes).
     * Uma prateleira real tem 5-40 candidatos; 60 é folga com teto.
     */
    public const MAX_CANDIDATES = 60;

    /** Largura máxima tratável, em mm (500cm). Prateleira maior que isso não existe na prática. */
    public const MAX_CAPACITY_MM = 5000;

    /** Teto absoluto de frentes por produto numa prateleira — trava de sanidade contra dado ruim. */
    public const MAX_FACINGS = 12;

    /**
     * Peso de ESTAR na prateleira (prioridade ①).
     *
     * Dominância garantida por construção: deixar um SKU de fora custa no mínimo
     * 10000 × 0,5 = 5000, enquanto o máximo que se ganha com TODA a ocupação da maior
     * prateleira possível (500cm × 1,0 = 500) mais TODAS as frentes extras de todos os
     * candidatos (60 × H(12) ≈ 187) é ~687. 5000 ≫ 687 — nenhuma sobra de espaço, por maior
     * que seja, compensa perder um produto.
     */
    private const INCLUSION_WEIGHT = 10000.0;

    /**
     * Peso de cada centímetro ocupado (prioridade ②).
     *
     * 1,0 por cm coloca a ocupação acima da distribuição de frentes: ganhar 1cm de gôndola
     * fechada (+1,0) vale mais que a diferença típica de preferência entre dar a frente a um
     * produto ou a outro (0,05 a 0,5). É isso que faz o empacotador procurar o encaixe EXATO
     * em vez de parar no "já está bom".
     */
    private const FILL_WEIGHT = 1.0;

    /** Estado inviável no DP (ex.: um obrigatório que não cabe naquele orçamento). */
    private const NEG_INF = -1.0e18;

    /** O DP trabalha em milímetros inteiros; a conta de verdade continua em cm float. */
    private const MM_PER_CM = 10.0;

    /**
     * Resolve a prateleira.
     *
     * @param  list<PackCandidate>  $candidates  Em ordem de ranking (o índice é a identidade).
     * @param  float  $available  Largura útil da prateleira em cm.
     * @param  float  $spacing  Folga entre produtos vizinhos em cm.
     * @return array<int, int>|null Frentes por índice de candidato (0 = ficou de fora).
     *                              `null` = não foi possível resolver — o chamador mantém o guloso.
     */
    public function pack(array $candidates, float $available, float $spacing = 0.0): ?array
    {
        $count = count($candidates);

        if ($count === 0 || $available <= 0.0) {
            return null;
        }

        if ($count > self::MAX_CANDIDATES) {
            Log::debug('ShelfKnapsackPacker: candidatos acima do teto — mantendo o guloso', [
                'candidatos' => $count,
                'teto' => self::MAX_CANDIDATES,
            ]);

            return null;
        }

        /*
         * A folga só existe ENTRE produtos, mas modelar isso no DP exigiria saber quantos
         * produtos entraram — informação que o DP só tem no fim. O truque: cada produto
         * colocado paga a folga (custo = largura + folga) e a capacidade ganha uma folga
         * de brinde. Então  Σlarguras + k·folga ≤ disponível + folga  ⟺
         * Σlarguras + (k−1)·folga ≤ disponível — exatamente a regra física, sem aproximação.
         */
        $capacityMm = (int) floor(($available + $spacing) * self::MM_PER_CM + PlacementMath::WIDTH_EPSILON_CM);

        if ($capacityMm <= 0 || $capacityMm > self::MAX_CAPACITY_MM) {
            return null;
        }

        $choices = $this->buildChoices($candidates, $capacityMm, $spacing);

        if ($choices === null) {
            return null;
        }

        return $this->solve($choices, $capacityMm, $count);
    }

    /**
     * Enumera, para cada candidato, as frentes viáveis com seu custo em mm e seu valor.
     *
     * @param  list<PackCandidate>  $candidates
     * @return array<int, list<array{0: int, 1: int, 2: float}>>|null [frentes, custoMm, valor]
     */
    private function buildChoices(array $candidates, int $capacityMm, float $spacing): ?array
    {
        $harmonic = $this->harmonicTable();
        $choices = [];

        foreach ($candidates as $index => $candidate) {
            $options = [];

            // Ficar de fora só é opção para quem não é obrigatório (custo e valor zero).
            if (! $candidate->forced) {
                $options[] = [0, 0, 0.0];
            }

            $minFacings = max(1, $candidate->minFacings);
            $maxFacings = min(max($candidate->maxFacings, $minFacings), self::MAX_FACINGS);

            for ($facings = $minFacings; $facings <= $maxFacings; $facings++) {
                /*
                 * Arredondamento para CIMA: o DP nunca pode prometer um encaixe que a prateleira
                 * real não comporta. A folga perdida nesse arredondamento (< 1mm por produto) é
                 * recuperada depois, em float exato, pela expansão de frentes do motor.
                 */
                $costMm = (int) ceil(
                    ($candidate->singleWidth * $facings + $spacing) * self::MM_PER_CM - PlacementMath::WIDTH_EPSILON_CM
                );

                if ($costMm > $capacityMm) {
                    break;
                }

                // A folga entre produtos NÃO conta como ocupação: ela é espaço vazio necessário,
                // não gôndola cheia. Só a largura do produto entra no termo de ocupação.
                $options[] = [
                    $facings,
                    $costMm,
                    self::INCLUSION_WEIGHT * $candidate->inclusionScore
                        + self::FILL_WEIGHT * ($candidate->singleWidth * $facings)
                        + $candidate->facingWeight * $harmonic[$facings],
                ];
            }

            // Obrigatório que não cabe nem com a frente mínima: o modelo está inconsistente com
            // o que o guloso decidiu. Aborta e deixa o guloso valer, em vez de perder o produto.
            if ($options === []) {
                Log::debug('ShelfKnapsackPacker: obrigatório não cabe na largura — mantendo o guloso', [
                    'largura_unitaria' => $candidate->singleWidth,
                    'frentes_minimas' => $candidate->minFacings,
                    'capacidade_mm' => $capacityMm,
                ]);

                return null;
            }

            $choices[$index] = $options;
        }

        return $choices;
    }

    /**
     * Programação dinâmica sobre a largura: dp[c] = melhor valor cabendo em c milímetros.
     *
     * Cada candidato é um GRUPO de escolhas mutuamente exclusivas (0, min, min+1, … max), por
     * isso a tabela é reconstruída a cada candidato em vez do laço invertido da mochila 0/1.
     *
     * @param  array<int, list<array{0: int, 1: int, 2: float}>>  $choices
     * @return array<int, int>|null
     */
    private function solve(array $choices, int $capacityMm, int $count): ?array
    {
        // Nenhum candidato processado ainda: valor zero para qualquer orçamento.
        $previous = array_fill(0, $capacityMm + 1, 0.0);

        /** @var array<int, array<int, int>> $taken taken[i][c] = escolha do candidato i com orçamento c */
        $taken = [];

        for ($index = 0; $index < $count; $index++) {
            $current = array_fill(0, $capacityMm + 1, self::NEG_INF);
            $picked = array_fill(0, $capacityMm + 1, -1);

            foreach ($choices[$index] as $option => [$facings, $costMm, $value]) {
                for ($budget = $costMm; $budget <= $capacityMm; $budget++) {
                    $source = $previous[$budget - $costMm];

                    if ($source === self::NEG_INF) {
                        continue;
                    }

                    $candidateValue = $source + $value;

                    if ($candidateValue > $current[$budget]) {
                        $current[$budget] = $candidateValue;
                        $picked[$budget] = $option;
                    }
                }
            }

            $previous = $current;
            $taken[$index] = $picked;
        }

        // Todos os obrigatórios juntos não cabem: mantém o guloso.
        if ($previous[$capacityMm] === self::NEG_INF) {
            return null;
        }

        // Reconstrução: desce do último candidato devolvendo o orçamento consumido por cada um.
        $facingsByCandidate = [];
        $budget = $capacityMm;

        for ($index = $count - 1; $index >= 0; $index--) {
            $option = $taken[$index][$budget];

            if ($option < 0) {
                return null;
            }

            [$facings, $costMm] = $choices[$index][$option];
            $facingsByCandidate[$index] = $facings;
            $budget -= $costMm;
        }

        ksort($facingsByCandidate);

        return $facingsByCandidate;
    }

    /**
     * Série harmônica acumulada: H(f) = 1 + 1/2 + … + 1/f.
     *
     * Modela o retorno decrescente das frentes — a 2ª frente de um produto agrega bem mais
     * que a 6ª. Sem isso o DP concentraria a prateleira inteira no produto de maior peso.
     *
     * @return array<int, float>
     */
    private function harmonicTable(): array
    {
        $table = [0 => 0.0];
        $sum = 0.0;

        for ($facings = 1; $facings <= self::MAX_FACINGS; $facings++) {
            $sum += 1.0 / $facings;
            $table[$facings] = $sum;
        }

        return $table;
    }
}
