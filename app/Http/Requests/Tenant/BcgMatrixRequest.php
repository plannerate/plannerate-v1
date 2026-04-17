<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class BcgMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return route('tenant.analysis.bcg.index');
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|string|exists:tenant.categories,id',
            'eans' => 'nullable|array',
            'eans.*' => 'string|max:13',

            'table_type' => 'nullable|string|in:sales,monthly_summaries',

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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->client_id && ! config('app.current_client_id')) {
                $validator->errors()->add('client_id', 'O client_id é obrigatório para análise BCG.');
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
