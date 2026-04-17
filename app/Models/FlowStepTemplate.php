<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate as ModelsFlowStepTemplate;
use Illuminate\Support\Str;


class FlowStepTemplate extends ModelsFlowStepTemplate
{



    /**
     * Retorna os templates padrão (ex.: planograma).
     * Use flow:seed-templates para criar no banco.
     *
     * @return array<int, array{name: string, slug: string, description: string, instructions: string, category: string, suggested_order: int, estimated_duration_days: int, is_required_by_default: bool, color: string, icon: string, tags: array}>
     */
    public static function getDefaultTemplates(): array
    {
        $defaults = [
            [
                'name' => 'Criação do planograma',
                'description' => 'Criação inicial do planograma com definição de produtos e layout',
                'instructions' => 'Definir produtos, posicionamento e layout inicial do planograma',
                'category' => 'criacao',
                'suggested_order' => 1,
                'estimated_duration_days' => 2,
                'is_required_by_default' => true,
                'color' => 'blue',
                'icon' => 'layout-grid',
                'tags' => ['inicial', 'obrigatoria'],
            ],
            [
                'name' => 'Revisão de imagens',
                'description' => 'Revisão das imagens utilizadas no planograma',
                'instructions' => 'Validar qualidade, padrão e consistência visual das imagens',
                'category' => 'revisao',
                'suggested_order' => 2,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'indigo',
                'icon' => 'image',
                'tags' => ['revisao', 'imagens'],
            ],
            [
                'name' => 'Revisão de dimensões',
                'description' => 'Revisão das dimensões e medidas do planograma',
                'instructions' => 'Conferir medidas de gôndolas, módulos e espaçamentos',
                'category' => 'revisao',
                'suggested_order' => 3,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'gray',
                'icon' => 'ruler',
                'tags' => ['revisao', 'dimensoes'],
            ],
            [
                'name' => 'Aprovação comercial',
                'description' => 'Validação comercial do planograma proposto',
                'instructions' => 'Aprovar estratégia comercial, margem e objetivos de venda',
                'category' => 'aprovacao',
                'suggested_order' => 4,
                'estimated_duration_days' => 2,
                'is_required_by_default' => true,
                'color' => 'yellow',
                'icon' => 'trending-up',
                'tags' => ['aprovacao', 'comercial'],
            ],
            [
                'name' => 'Aprovação da área de GC',
                'description' => 'Aprovação pela área de Gerenciamento de Categoria',
                'instructions' => 'Validar alinhamento com estratégia de categoria e políticas',
                'category' => 'aprovacao',
                'suggested_order' => 5,
                'estimated_duration_days' => 2,
                'is_required_by_default' => true,
                'color' => 'purple',
                'icon' => 'check-circle',
                'tags' => ['aprovacao', 'gc'],
            ],
            [
                'name' => 'Execução loja',
                'description' => 'Implementação do planograma na loja',
                'instructions' => 'Implementar fisicamente o planograma na loja',
                'category' => 'execucao',
                'suggested_order' => 6,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'red',
                'icon' => 'store',
                'tags' => ['execucao', 'loja'],
            ],
            [
                'name' => 'Revisão periódica',
                'description' => 'Revisão recorrente do planograma em operação',
                'instructions' => 'Acompanhar desempenho e realizar ajustes periódicos',
                'category' => 'revisao',
                'suggested_order' => 7,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'blue',
                'icon' => 'refresh-cw',
                'tags' => ['revisao', 'periodica'],
            ],
        ];

        foreach ($defaults as $index => $row) {
            $defaults[$index]['slug'] = Str::slug($row['name']);
        }

        return $defaults;
    }
}
