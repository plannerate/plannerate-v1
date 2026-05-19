<?php

namespace App\Services\AutoPlanogram\Template;

/**
 * Analisa o resultado por slot e gera sugestões acionáveis para o especialista.
 *
 * @phpstan-type SlotAnalysis array{
 *   slot_id: string,
 *   grouping: string,
 *   module_number: int,
 *   shelf_order: int,
 *   shelf_id: string,
 *   largura_total: float,
 *   largura_usada: float,
 *   largura_livre: float,
 *   percentual_uso: int,
 *   produtos_posicionados: int,
 *   produtos_rejeitados: int,
 *   produtos_rejeitados_nomes: list<string>,
 * }
 * @phpstan-type Suggestion array{
 *   tipo: string,
 *   prioridade: string,
 *   slot_id?: string,
 *   mensagem: string,
 *   acao: string,
 *   dados: array<string, mixed>,
 * }
 */
final class SlotSuggestionGenerator
{
    private const ESPACO_MINIMO_CM = 10;

    /**
     * Analisa os slots e gera sugestões acionáveis.
     *
     * @param  list<SlotAnalysis>  $slotAnalysis
     * @return list<Suggestion>
     */
    public function generate(array $slotAnalysis): array
    {
        if (empty($slotAnalysis)) {
            return [];
        }

        $suggestions = [];
        $slots = collect($slotAnalysis);

        $slotsComEspaco = $slots->filter(fn ($s) => $s['largura_livre'] > self::ESPACO_MINIMO_CM);
        $slotsComRejeitos = $slots->filter(fn ($s) => $s['produtos_rejeitados'] > 0);

        foreach ($slotsComEspaco as $slot) {
            $prioridade = $slot['largura_livre'] > 30 ? 'alta' : 'media';
            $suggestions[] = [
                'tipo' => 'espaco_disponivel',
                'prioridade' => $prioridade,
                'slot_id' => $slot['slot_id'],
                'mensagem' => "Prat #{$slot['shelf_order']} ({$slot['grouping']}) tem {$slot['largura_livre']}cm livres ({$slot['percentual_uso']}% usado).",
                'acao' => "Considere adicionar mais produtos ao grouping \"{$slot['grouping']}\" no template.",
                'dados' => [
                    'largura_livre' => $slot['largura_livre'],
                    'percentual_uso' => $slot['percentual_uso'],
                    'grouping' => $slot['grouping'],
                    'shelf_order' => $slot['shelf_order'],
                    'module_number' => $slot['module_number'],
                ],
            ];
        }

        if ($slotsComRejeitos->isNotEmpty()) {
            $totalRejeitados = $slotsComRejeitos->sum('produtos_rejeitados');
            $groupingsAfetados = $slotsComRejeitos->pluck('grouping')->unique()->values()->toArray();
            $produtosFora = $slotsComRejeitos->flatMap(fn ($s) => $s['produtos_rejeitados_nomes'])->toArray();

            $suggestions[] = [
                'tipo' => 'capacidade_excedida',
                'prioridade' => 'alta',
                'mensagem' => "{$totalRejeitados} produto(s) não couberam nos slots do template.",
                'acao' => 'Considere criar um subtemplate com mais módulos ou reduzir o número de produtos nesses groupings.',
                'dados' => [
                    'total_rejeitados' => $totalRejeitados,
                    'groupings_cheios' => $groupingsAfetados,
                    'produtos_fora' => $produtosFora,
                ],
            ];
        }

        return collect($suggestions)
            ->sortBy(fn ($s) => $s['prioridade'] === 'alta' ? 0 : 1)
            ->values()
            ->toArray();
    }
}
