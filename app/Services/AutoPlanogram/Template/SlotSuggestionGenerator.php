<?php

namespace App\Services\AutoPlanogram\Template;

/**
 * Analisa o resultado por slot e gera sugestões acionáveis para o especialista.
 *
 * @phpstan-type SlotAnalysis array{
 *   slot_id: string,
 *   category_id: string,
 *   category_name: string,
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
     * Percentual de ocupação acima do qual um slot é considerado sob pressão de capacidade.
     * Sugere adicionar módulos ou reduzir o mix quando atingido com rejeições intermediárias.
     */
    private const PRESSAO_PERCENTUAL = 85;

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
                'mensagem' => "Prat #{$slot['shelf_order']} ({$slot['category_name']}) tem {$slot['largura_livre']}cm livres ({$slot['percentual_uso']}% usado).",
                'acao' => "Considere adicionar mais produtos à categoria \"{$slot['category_name']}\" no template.",
                'dados' => [
                    'largura_livre' => $slot['largura_livre'],
                    'percentual_uso' => $slot['percentual_uso'],
                    'category_id' => $slot['category_id'],
                    'category_name' => $slot['category_name'],
                    'shelf_order' => $slot['shelf_order'],
                    'module_number' => $slot['module_number'],
                ],
            ];
        }

        if ($slotsComRejeitos->isNotEmpty()) {
            $totalRejeitados = $slotsComRejeitos->sum('produtos_rejeitados');
            $groupingsAfetados = $slotsComRejeitos->pluck('category_name')->unique()->values()->toArray();
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

        // Alerta de pressão de capacidade: categorias com média de ocupação > 85%.
        // Indica que a gôndola está na borda da capacidade para esse agrupamento —
        // qualquer crescimento do mix resultará em rejeições. Sugere adicionar módulos.
        $categoriasComPressao = $slots
            ->filter(fn ($s) => $s['percentual_uso'] >= self::PRESSAO_PERCENTUAL)
            ->groupBy('category_name')
            ->map(fn ($group, $name) => [
                'category_name' => $name,
                'category_id' => $group->first()['category_id'],
                'avg_uso' => (int) round($group->avg('percentual_uso')),
                'max_uso' => $group->max('percentual_uso'),
                'num_slots' => $group->count(),
            ])
            ->values()
            ->filter(fn ($c) => $c['num_slots'] >= 2); // pelo menos 2 slots saturados = padrão

        foreach ($categoriasComPressao as $cat) {
            $suggestions[] = [
                'tipo' => 'pressao_capacidade',
                'prioridade' => $cat['max_uso'] >= 95 ? 'alta' : 'media',
                'mensagem' => "Categoria \"{$cat['category_name']}\" está saturada: "
                    ."{$cat['num_slots']} slot(s) com média de {$cat['avg_uso']}% de ocupação "
                    ."(máx. {$cat['max_uso']}%).",
                'acao' => 'Considere adicionar 1 módulo para reduzir a pressão nessa categoria '
                    .'ou remover produtos de menor giro do mix.',
                'dados' => [
                    'category_name' => $cat['category_name'],
                    'category_id' => $cat['category_id'],
                    'avg_uso_percentual' => $cat['avg_uso'],
                    'max_uso_percentual' => $cat['max_uso'],
                    'num_slots_saturados' => $cat['num_slots'],
                ],
            ];
        }

        return collect($suggestions)
            ->sortBy(fn ($s) => $s['prioridade'] === 'alta' ? 0 : 1)
            ->values()
            ->toArray();
    }
}
