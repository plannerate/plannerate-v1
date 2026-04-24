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
