<?php

namespace App\Http\Requests\Tenant;

use App\Models\Category;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return $category && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');
        $tenantId = $this->tenantId();

        return [
            'category_id' => ['nullable', 'ulid', 'different:id', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->where('tenant_id', $tenantId)->ignore($category)],
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
