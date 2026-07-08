<?php

namespace App\Http\Requests\Landlord;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida a movimentação de produtos de uma categoria para outra.
 *
 * A autorização é feita no controller (`authorize('update', $tenant)`) e a
 * existência das entidades é validada no serviço, dentro do contexto do tenant.
 */
class MoveProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['string', 'distinct'],
            'target_category_id' => ['required', 'string'],
        ];
    }
}
