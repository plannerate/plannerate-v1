<?php

namespace App\Http\Requests\Landlord;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantGondolaDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');

        return $tenant && ($this->user()?->can('update', $tenant) ?? false);
    }

    /**
     * Regras espelham o StoreGondolaRequest do pacote (estrutura física da
     * gôndola), exceto campos por-instância (nome auto-gerado, workflow, geração).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Geral
            'location' => ['nullable', 'string', 'max:255'],
            'side' => ['required', 'string', 'max:50'],
            'scaleFactor' => ['required', 'numeric', 'min:1'],
            'flow' => ['required', 'in:left_to_right,right_to_left'],

            // Módulo
            'height' => ['required', 'numeric', 'min:1'],
            'width' => ['required', 'numeric', 'min:1'],
            'numModules' => ['required', 'integer', 'min:1'],

            // Base
            'baseHeight' => ['required', 'numeric', 'min:1'],
            'baseWidth' => ['required', 'numeric', 'min:1'],
            'baseDepth' => ['required', 'numeric', 'min:1'],

            // Cremalheira
            'rackWidth' => ['required', 'numeric', 'min:1'],
            'holeHeight' => ['required', 'numeric', 'min:1'],
            'holeWidth' => ['required', 'numeric', 'min:1'],
            'holeSpacing' => ['required', 'numeric', 'min:1'],

            // Prateleiras
            'shelfHeight' => ['required', 'numeric', 'min:1'],
            'shelfWidth' => ['required', 'numeric', 'min:1'],
            'shelfDepth' => ['required', 'numeric', 'min:1'],
            'numShelves' => ['required', 'integer', 'min:0'],
            'productType' => ['required', 'in:normal,hook'],
        ];
    }
}
