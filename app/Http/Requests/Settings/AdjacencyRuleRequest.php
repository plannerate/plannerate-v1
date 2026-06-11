<?php

namespace App\Http\Requests\Settings;

use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Models\AdjacencyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjacencyRuleRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var AdjacencyRule|null $adjacencyRule */
        $adjacencyRule = $this->route('adjacencyRule');

        $categoriesTable = $this->tenantTable('categories');
        $adjacencyTable = $this->tenantTable('adjacency_rules');
        $tenantId = $this->tenantId();

        return [
            'source_category_id' => [
                'required',
                'string',
                'size:26',
                'different:target_category_id',
                Rule::exists($categoriesTable, 'id'),
            ],
            'target_category_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists($categoriesTable, 'id'),
                Rule::unique($adjacencyTable)
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('source_category_id', $this->string('source_category_id')->toString())
                    )
                    ->ignore($adjacencyRule),
            ],
            'rule_type' => ['required', Rule::in(['must_be_near', 'must_avoid', 'prefer_near'])],
            'weight' => ['required', 'numeric', 'min:-100', 'max:100'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
