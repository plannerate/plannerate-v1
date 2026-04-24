<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de Validação para Geração de Planogramas com IA
 */
class IAGeneratePlanogramRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Usuário deve estar autenticado (validado via middleware)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Campos do algoritmo tradicional (compatibilidade)
            'strategy' => ['required', 'string', 'in:abc,sales,margin,mix'],
            'use_existing_analysis' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'min_facings' => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_facings' => ['nullable', 'integer', 'min:1', 'max:20'],
            'group_by_subcategory' => ['nullable', 'boolean'],
            'include_products_without_sales' => ['nullable', 'boolean'],
            'table_type' => ['nullable', 'string', 'in:sales,monthly_summaries'],

            // Campos específicos de IA (opcionais)
            'category_id' => ['nullable', 'string', 'size:26'], // ULID
            'subcategory_id' => ['nullable', 'string', 'size:26'],
            'brand_id' => ['nullable', 'string', 'size:26'],

            // Features de IA
            'respect_seasonality' => ['nullable', 'boolean'],
            'apply_visual_grouping' => ['nullable', 'boolean'],
            'intelligent_ordering' => ['nullable', 'boolean'],
            'load_balancing' => ['nullable', 'boolean'],
            'additional_instructions' => ['nullable', 'string', 'max:1000'],

            // Configuração IA
            'model' => ['nullable', 'string', 'in:gpt-4o,gpt-4o-mini,claude-sonnet-4-20250514,claude-3-5-haiku-latest,claude-sonnet-4-6'],
            'max_tokens' => ['nullable', 'integer', 'min:1000', 'max:16000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Categoria é obrigatória',
            'category_id.size' => 'ID de categoria inválido',
            'strategy.required' => 'Estratégia de geração é obrigatória',
            'strategy.enum' => 'Estratégia inválida. Use: sales, margin, mix ou abc',
            'subcategory_id.size' => 'ID de subcategoria inválido',
            'brand_id.size' => 'ID de marca inválido',
            'additional_instructions.max' => 'Instruções adicionais devem ter no máximo 1000 caracteres',
            'model.in' => 'Modelo de IA inválido',
            'max_tokens.min' => 'Tokens mínimos: 1000',
            'max_tokens.max' => 'Tokens máximos: 16000',
            'temperature.min' => 'Temperatura mínima: 0',
            'temperature.max' => 'Temperatura máxima: 2',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'categoria',
            'strategy' => 'estratégia',
            'subcategory_id' => 'subcategoria',
            'brand_id' => 'marca',
            'respect_seasonality' => 'respeitar sazonalidade',
            'apply_visual_grouping' => 'agrupamento visual',
            'intelligent_ordering' => 'ordenação inteligente',
            'load_balancing' => 'balanceamento',
            'additional_instructions' => 'instruções adicionais',
            'model' => 'modelo de IA',
            'max_tokens' => 'tokens máximos',
            'temperature' => 'temperatura',
        ];
    }
}
