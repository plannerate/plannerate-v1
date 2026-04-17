<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class AbcAnalysisRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição
     */
    public function authorize(): bool
    {
        return true; // Ajustar conforme necessário
    }

    /**
     * Obtém a URL para redirecionar em caso de falha na validação
     */
    protected function getRedirectUrl(): string
    {
        return route('tenant.analysis.abc.index');
    }

    /**
     * Regras de validação
     */
    public function rules(): array
    {
        return [
            // Aceita categoria_id OU eans (array)
            'category_id' => 'nullable|string|exists:tenant.categories,id',
            'eans' => 'nullable|array',
            'eans.*' => 'string|max:13',

            // Tipo de tabela a usar
            'table_type' => 'nullable|string|in:sales,monthly_summaries',

            // Pesos para cálculo (opcionais)
            'peso_qtde' => 'nullable|numeric|min:0',
            'peso_valor' => 'nullable|numeric|min:0',
            'peso_margem' => 'nullable|numeric|min:0',

            // Cortes para classificação ABC (opcionais)
            'corte_a' => 'nullable|numeric|min:0|max:1',
            'corte_b' => 'nullable|numeric|min:0|max:1',

            // Filtros adicionais
            'client_id' => 'nullable|string|exists:tenant.clients,id',
            'store_id' => 'nullable|string|exists:tenant.stores,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'month_from' => 'nullable|date',
            'month_to' => 'nullable|date',
        ];
    }

    /**
     * Mensagens de validação personalizadas
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'A categoria selecionada não existe.',
            'eans.array' => 'Os EANs devem ser fornecidos como um array.',
            'table_type.in' => 'O tipo de tabela deve ser "sales" ou "monthly_summaries".',
            'corte_a.max' => 'O corte A deve ser um valor entre 0 e 1 (percentual).',
            'corte_b.max' => 'O corte B deve ser um valor entre 0 e 1 (percentual).',
        ];
    }

    /**
     * Validação customizada: deve ter categoria_id OU eans, e client_id é obrigatório
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // client_id é obrigatório
            if (! $this->client_id && ! config('app.current_client_id')) {
                $validator->errors()->add('client_id', 'O client_id é obrigatório para realizar a análise ABC.');
            }

            // Deve ter categoria_id OU eans
            if (! $this->category_id && empty($this->eans)) {
                $validator->errors()->add('category_id', 'É necessário fornecer uma categoria ou uma lista de EANs.');
                $validator->errors()->add('eans', 'É necessário fornecer uma categoria ou uma lista de EANs.');
            }

            // Não pode ter ambos
            if ($this->category_id && ! empty($this->eans)) {
                $validator->errors()->add('category_id', 'Forneça apenas categoria OU EANs, não ambos.');
                $validator->errors()->add('eans', 'Forneça apenas categoria OU EANs, não ambos.');
            }
        });
    }
}
