<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Analysis;

use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Illuminate\Support\Collection;

/**
 * Espaço ocupado por produto numa gôndola (frentes e share linear).
 *
 * É o eixo que falta às matrizes de quadrantes vendidas por BIs de varejo: eles
 * sabem quanto o produto vende, mas não quanto de gôndola ele custa. Cruzar o
 * quadrante da Análise BCG com o share de gôndola é o que transforma diagnóstico
 * em ação de planograma:
 *
 *   - alto valor  + pouco espaço → aumentar frentes
 *   - baixo valor + muito espaço → reduzir frentes (é onde está o dinheiro)
 *
 * FÓRMULA (idêntica à do editor, ver resources/js/.../editor/Layer.vue):
 * cada layer desenha `quantity` cópias do produto lado a lado, cada uma com
 * `product.width` cm de largura.
 *
 *   espaco_linear_cm(produto) = Σ (layer.quantity × product.width)
 *   share_gondola(produto)    = espaco_linear_cm ÷ Σ espaco_linear_cm(todos) × 100
 *
 * O `facingGap`/`spacing` NÃO entra: é um respiro de renderização em px entre as
 * frentes, não a pegada física do produto.
 *
 * A agregação é feita em PHP, não em SQL, de propósito: uma gôndola tem centenas
 * de layers (não milhões), e somar no banco exigiria GREATEST/MAX de dois argumentos
 * para o clamp de quantidade — que diverge entre PostgreSQL (produção) e SQLite
 * (testes), a mesma armadilha que já obrigou a pular o teste do STDDEV_POP.
 *
 * Testes: tests/Unit/Services/Analysis/GondolaSpaceServiceTest.php
 */
class GondolaSpaceService
{
    /**
     * Espaço ocupado por cada produto fisicamente alocado na gôndola.
     *
     * @return array<string, array{facings: int, espaco_linear_cm: float, share_gondola: float, sem_dimensao: bool}>
     */
    public function spaceByProduct(string $gondolaId): array
    {
        $rows = Layer::query()
            ->join('segments', 'segments.id', '=', 'layers.segment_id')
            ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
            ->join('sections', 'sections.id', '=', 'shelves.section_id')
            ->join('products', 'products.id', '=', 'layers.product_id')
            ->where('sections.gondola_id', $gondolaId)
            ->whereNotNull('layers.product_id')
            ->whereNull('layers.deleted_at')
            ->whereNull('segments.deleted_at')
            ->whereNull('shelves.deleted_at')
            ->whereNull('sections.deleted_at')
            ->select([
                'layers.product_id',
                'layers.quantity',
                'products.width',
            ])
            ->get()
            ->toBase();

        return $this->aggregate($rows);
    }

    /**
     * Etapa pura: soma frentes e espaço linear por produto e calcula o share.
     * Não consulta o banco.
     *
     * Um produto pode aparecer em vários layers (segmentos e prateleiras diferentes)
     * — todas as ocorrências somam.
     *
     * @param  Collection  $rows  Itens com product_id, quantity, width
     * @return array<string, array{facings: int, espaco_linear_cm: float, share_gondola: float, sem_dimensao: bool}>
     */
    public function aggregate(Collection $rows): array
    {
        $byProduct = [];

        foreach ($rows as $row) {
            $productId = $row->product_id;

            // O editor trata quantidade ausente/zerada como 1 frente (Layer.vue),
            // e um produto exposto sempre ocupa ao menos uma frente.
            $facings = max(1, (int) ($row->quantity ?? 1));
            $width = (float) ($row->width ?? 0);

            if (! isset($byProduct[$productId])) {
                $byProduct[$productId] = [
                    'facings' => 0,
                    'espaco_linear_cm' => 0.0,
                    // Produto sem largura cadastrada ocuparia 0 cm e pareceria "espremido"
                    // na gôndola — o que dispararia "aumentar frentes" sem fundamento.
                    // O share fica errado por falta de dado, então isso precisa ser visível.
                    'sem_dimensao' => $width <= 0,
                ];
            }

            $byProduct[$productId]['facings'] += $facings;
            $byProduct[$productId]['espaco_linear_cm'] += $facings * $width;

            if ($width <= 0) {
                $byProduct[$productId]['sem_dimensao'] = true;
            }
        }

        $totalLinear = array_sum(array_column($byProduct, 'espaco_linear_cm'));

        foreach ($byProduct as $productId => $data) {
            $byProduct[$productId]['espaco_linear_cm'] = round($data['espaco_linear_cm'], 2);
            $byProduct[$productId]['share_gondola'] = $totalLinear > 0
                ? round(($data['espaco_linear_cm'] / $totalLinear) * 100, 4)
                : 0.0;
        }

        return $byProduct;
    }
}
