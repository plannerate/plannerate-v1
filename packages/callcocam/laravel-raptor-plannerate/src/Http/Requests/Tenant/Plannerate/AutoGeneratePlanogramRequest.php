<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de Validação para Geração Automática de Planogramas
 *
 * Valida todas as opções enviadas pelo modal do frontend
 */
class AutoGeneratePlanogramRequest extends FormRequest
{
    /**
     * Determinar se o usuário está autorizado a fazer essa requisição
     */
    public function authorize(): bool
    {
        // TODO: Adicionar verificações de permissão se necessário
        return true;
    }

    /**
     * Regras de validação
     */
    public function rules(): array
    {
        return [
            'strategy' => ['required', 'string', 'in:abc,sales,margin,mix'],
            'use_existing_analysis' => ['required', 'boolean'],
            'start_date' => ['nullable', 'date', 'required_if:use_existing_analysis,false'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_if:use_existing_analysis,false'],
            'min_facings' => ['required', 'integer', 'min:1', 'max:10'],
            'max_facings' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_facings'],
            'group_by_subcategory' => ['required', 'boolean'],
            'include_products_without_sales' => ['required', 'boolean'],
            'table_type' => ['required', 'string', 'in:sales,monthly_summaries'],
            'use_ai' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
        ];
    }

    /**
     * Mensagens de erro customizadas (em PT-BR)
     */
    public function messages(): array
    {
        return [
            'strategy.required' => 'A estratégia de otimização é obrigatória.',
            'strategy.in' => 'Estratégia inválida. Escolha: abc, sales, margin ou mix.',

            'start_date.required_if' => 'Data inicial é obrigatória quando não usar análise existente.',
            'start_date.date' => 'Data inicial inválida.',

            'end_date.required_if' => 'Data final é obrigatória quando não usar análise existente.',
            'end_date.date' => 'Data final inválida.',
            'end_date.after_or_equal' => 'Data final deve ser igual ou posterior à data inicial.',

            'min_facings.required' => 'Facings mínimo é obrigatório.',
            'min_facings.min' => 'Facings mínimo deve ser pelo menos 1.',
            'min_facings.max' => 'Facings mínimo não pode ser maior que 10.',

            'max_facings.required' => 'Facings máximo é obrigatório.',
            'max_facings.min' => 'Facings máximo deve ser pelo menos 1.',
            'max_facings.max' => 'Facings máximo não pode ser maior que 20.',
            'max_facings.gte' => 'Facings máximo deve ser maior ou igual ao mínimo.',

            'table_type.in' => 'Tipo de dados inválido. Escolha: sales ou monthly_summaries.',
            'category_id.exists' => 'Categoria inválida. A categoria selecionada não existe.',
            'category_id.string' => 'Categoria deve ser uma string (ID).',
        ];
    }

    /**
     * Atributos customizados para mensagens de erro
     */
    public function attributes(): array
    {
        return [
            'strategy' => 'estratégia',
            'use_existing_analysis' => 'usar análise existente',
            'start_date' => 'data inicial',
            'end_date' => 'data final',
            'min_facings' => 'facings mínimo',
            'max_facings' => 'facings máximo',
            'group_by_subcategory' => 'agrupar por subcategoria',
            'include_products_without_sales' => 'incluir produtos sem vendas',
            'table_type' => 'tipo de dados',
            'use_ai' => 'usar IA por section',
            'category_id' => 'categoria',
        ];
    }
}
