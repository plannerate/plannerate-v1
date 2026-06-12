<?php

namespace App\Http\Requests\Settings;

use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Callcocam\LaravelRaptorPlannerate\Models\ShelfLevelPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShelfLevelPreferenceRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ShelfLevelPreference|null $preference */
        $preference = $this->route('preference');

        $categoriesTable = $this->tenantTable('categories');
        $preferencesTable = $this->tenantTable('shelf_level_preferences');
        $tenantId = $this->tenantId();

        return [
            'category_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists($categoriesTable, 'id'),
                Rule::unique($preferencesTable)
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($preference),
            ],
            'preferred_level' => [
                'required',
                Rule::in(array_column(ShelfLevel::cases(), 'value')),
            ],
        ];
    }
}
