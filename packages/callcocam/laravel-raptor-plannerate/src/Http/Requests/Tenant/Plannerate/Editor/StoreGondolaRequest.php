<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor;

use Illuminate\Foundation\Http\FormRequest;

class StoreGondolaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Step 1: Basic Information
            'gondolaName' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'side' => ['required', 'string', 'max:50'],
            'scaleFactor' => ['required', 'numeric', 'min:1'],
            'flow' => ['required', 'in:left_to_right,right_to_left'],
            'status' => ['required', 'in:published,draft'],

            // Step 1: Generation mode (manual = blank/null)
            'mode' => ['nullable', 'in:manual,template,automatic'],
            'template_id' => ['nullable', 'string', 'required_if:mode,template'],

            // Step 2: Module Configuration
            'height' => ['required', 'numeric', 'min:1'],
            'width' => ['required', 'numeric', 'min:1'],
            'numModules' => ['required', 'integer', 'min:1'],

            // Step 3: Base Configuration
            'baseHeight' => ['required', 'numeric', 'min:1'],
            'baseWidth' => ['required', 'numeric', 'min:1'],
            'baseDepth' => ['required', 'numeric', 'min:1'],

            // Step 4: Cremalheira Configuration
            'rackWidth' => ['required', 'numeric', 'min:1'],
            'holeHeight' => ['required', 'numeric', 'min:1'],
            'holeWidth' => ['required', 'numeric', 'min:1'],
            'holeSpacing' => ['required', 'numeric', 'min:1'],

            // Step 5: Shelves Default Configuration
            'shelfHeight' => ['required', 'numeric', 'min:1'],
            'shelfWidth' => ['required', 'numeric', 'min:1'],
            'shelfDepth' => ['required', 'numeric', 'min:1'],
            'numShelves' => ['required', 'integer', 'min:0'],
            'productType' => ['required', 'in:normal,hook'],

            // Step 6: Workflow Configuration
            'autoStartWorkflow' => ['sometimes', 'boolean'],
            'assignToCurrentUser' => ['sometimes', 'boolean'],
            'assignedUserId' => ['sometimes', 'nullable', 'string'],
            'startDate' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // Step 1: subtemplate selecionado (modo template — modelo escolhido explicitamente)
            'subtemplate_id' => ['nullable', 'string', 'required_if:mode,template'],

            // Parâmetros de geração (modo automático — criado + gerado no mesmo fluxo).
            // Campos flat para reaproveitar os mesmos partials do AutomaticGenerateModal.
            'strategy' => ['sometimes', 'string', 'in:abc,sales,margin,mix'],
            'use_existing_analysis' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'nullable', 'string'],
            'end_date' => ['sometimes', 'nullable', 'string'],
            'min_facings' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'max_facings' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'group_by_subcategory' => ['sometimes', 'boolean'],
            'include_products_without_sales' => ['sometimes', 'boolean'],
            'table_type' => ['sometimes', 'string', 'in:sales,monthly_summaries'],
            'category_id' => ['sometimes', 'nullable', 'string'],
            'facing_expansion' => ['sometimes', 'nullable', 'string', 'in:none,score,current_stock,target_stock,equal'],
            'use_target_stock' => ['sometimes', 'nullable', 'boolean'],
            'space_fallback' => ['sometimes', 'nullable', 'string', 'in:reduce_c,reduce_facings,skip'],
            'max_share_per_sku' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'max_share_per_brand' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'max_share_per_subcategory' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'hot_zone_priority' => ['sometimes', 'nullable', 'string', 'in:none,maior_margem,maior_giro,maior_valor_vendido,curva_a'],
            'cold_zone_priority' => ['sometimes', 'nullable', 'string', 'in:none,menor_margem,complementar_fria,maior_volume,menor_prioridade'],
            'flow_direction' => ['sometimes', 'nullable', 'string', 'in:left_to_right,right_to_left'],
            'secondary_criteria' => ['sometimes', 'nullable', 'array'],
            'secondary_criteria.*.key' => ['required_with:secondary_criteria', 'string', 'in:marca,preco,tamanho,margem,embalagem,tipo,sabor,atributo'],
            'secondary_criteria.*.direction' => ['required_with:secondary_criteria', 'string', 'in:asc,desc,none'],
        ];
    }

    public function messages(): array
    {
        return [
            'gondolaName.required' => 'O nome da gôndola é obrigatório.',
            'side.required' => 'O lado do corredor é obrigatório.',
            'scaleFactor.required' => 'O fator de escala é obrigatório.',
            'scaleFactor.min' => 'O fator de escala deve ser no mínimo 1.',
            'flow.required' => 'A posição do fluxo é obrigatória.',
            'flow.in' => 'A posição do fluxo deve ser esquerda para direita ou direita para esquerda.',

            'template_id.required_if' => 'Selecione um template para o modo de geração por template.',

            'height.required' => 'A altura do módulo é obrigatória.',
            'height.min' => 'A altura do módulo deve ser no mínimo 1 cm.',
            'width.required' => 'A largura do módulo é obrigatória.',
            'width.min' => 'A largura do módulo deve ser no mínimo 1 cm.',
            'numModules.required' => 'O número de módulos é obrigatório.',
            'numModules.min' => 'Deve haver pelo menos 1 módulo.',

            'baseHeight.required' => 'A altura da base é obrigatória.',
            'baseWidth.required' => 'A largura da base é obrigatória.',
            'baseDepth.required' => 'A profundidade da base é obrigatória.',

            'rackWidth.required' => 'A largura da cremalheira é obrigatória.',
            'holeHeight.required' => 'A altura do furo é obrigatória.',
            'holeWidth.required' => 'A largura do furo é obrigatória.',
            'holeSpacing.required' => 'O espaçamento vertical é obrigatório.',

            'shelfHeight.required' => 'A espessura da prateleira é obrigatória.',
            'shelfWidth.required' => 'A largura da prateleira é obrigatória.',
            'shelfDepth.required' => 'A profundidade da prateleira é obrigatória.',
            'numShelves.required' => 'O número de prateleiras é obrigatório.',
            'numShelves.min' => 'O número de prateleiras deve ser no mínimo 0.',
            'productType.required' => 'O tipo de produto padrão é obrigatório.',
            'productType.in' => 'O tipo de produto deve ser normal ou gancheira.',
        ];
    }
}
