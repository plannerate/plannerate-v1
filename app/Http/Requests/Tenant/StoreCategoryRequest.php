<?php

namespace App\Http\Requests\Tenant;

use App\Models\Category;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $categoriesTable = $this->tenantTable('categories');

        return [
            'category_id' => ['nullable', 'ulid', Rule::exists($categoriesTable, 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique($categoriesTable, 'slug')->where('tenant_id', $tenantId)],
            'level_name' => ['nullable', 'string', 'max:255'],
            'codigo' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(['draft', 'published', 'importer'])],
            'description' => ['nullable', 'string', 'max:255'],
            'nivel' => ['nullable', 'string', 'max:255'],
            'hierarchy_position' => ['nullable', 'integer', 'between:1,7'],
            'full_path' => ['nullable', 'string'],
            'hierarchy_path' => ['nullable', 'array'],
            'is_placeholder' => ['sometimes', 'boolean'],
        ];
    }
}
