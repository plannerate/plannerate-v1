<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class TargetStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return route('tenant.analysis.target-stock.index');
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|string|exists:tenant.categories,id',
            'eans' => 'nullable|array',
            'eans.*' => 'string|max:13',

            'table_type' => 'nullable|string|in:sales,monthly_summaries',

            // Pesos ABC
            'peso_qtde' => 'nullable|numeric|min:0',
            'peso_valor' => 'nullable|numeric|min:0',
            'peso_margem' => 'nullable|numeric|min:0',

            // Cortes ABC
            'corte_a' => 'nullable|numeric|min:0|max:1',
            'corte_b' => 'nullable|numeric|min:0|max:1',

            // Parâmetros de estoque alvo
            'period_type' => 'nullable|string|in:daily,monthly',
            'nivel_servico_a' => 'nullable|numeric|min:0|max:1',
            'nivel_servico_b' => 'nullable|numeric|min:0|max:1',
            'nivel_servico_c' => 'nullable|numeric|min:0|max:1',
            'cobertura_dias_a' => 'nullable|integer|min:1',
            'cobertura_dias_b' => 'nullable|integer|min:1',
            'cobertura_dias_c' => 'nullable|integer|min:1',

            // Filtros
            'client_id' => 'nullable|string|exists:tenant.clients,id',
            'store_id' => 'nullable|string|exists:tenant.stores,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'month_from' => 'nullable|date',
            'month_to' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'A categoria selecionada não existe.',
            'table_type.in' => 'O tipo de tabela deve ser "sales" ou "monthly_summaries".',
            'period_type.in' => 'O tipo de período deve ser "daily" ou "monthly".',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->client_id && ! config('app.current_client_id')) {
                $validator->errors()->add('client_id', 'O client_id é obrigatório para análise de estoque alvo.');
            }

            if (! $this->category_id && empty($this->eans)) {
                $validator->errors()->add('category_id', 'É necessário fornecer uma categoria ou uma lista de EANs.');
                $validator->errors()->add('eans', 'É necessário fornecer uma categoria ou uma lista de EANs.');
            }

            if ($this->category_id && ! empty($this->eans)) {
                $validator->errors()->add('category_id', 'Forneça apenas categoria OU EANs, não ambos.');
                $validator->errors()->add('eans', 'Forneça apenas categoria OU EANs, não ambos.');
            }
        });
    }
}
