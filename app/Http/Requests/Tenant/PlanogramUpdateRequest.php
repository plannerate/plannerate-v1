<?php

namespace App\Http\Requests\Tenant;

use App\Models\Gondola;
use App\Models\Planogram;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanogramUpdateRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Planogram|null $planogram */
        $planogram = $this->route('planogram');

        return $planogram && ($this->user()?->can('update', $planogram) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Planogram $planogram */
        $planogram = $this->route('planogram');
        $tenantId = $this->tenantId();
        $storesTable = $this->tenantTable('stores');
        $clustersTable = $this->tenantTable('clusters');
        $planogramsTable = $this->tenantTable('planograms');
        $categoriesTable = $this->tenantTable('categories');

        return [
            'template_id' => ['nullable', 'string', 'max:255'],
            'store_id' => ['required', 'ulid', Rule::exists($storesTable, 'id')->where('tenant_id', $tenantId), $this->storeChangeNotBlockedByMapLinksRule($planogram)],
            'cluster_id' => ['nullable', 'ulid', Rule::exists($clustersTable, 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique($planogramsTable, 'slug')->where('tenant_id', $tenantId)->ignore($planogram)],
            'type' => ['required', Rule::in(['realograma', 'planograma'])],
            'category_id' => ['required', 'ulid', Rule::exists($categoriesTable, 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }

    /**
     * Impede trocar a loja do planograma enquanto houver gôndolas vinculadas ao mapa.
     *
     * Os vínculos (`gondolas.linked_map_gondola_id`) apontam para regiões da loja atual;
     * trocar a loja os deixaria órfãos. O usuário deve desvincular as gôndolas antes.
     */
    private function storeChangeNotBlockedByMapLinksRule(Planogram $planogram): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($planogram): void {
            $newStoreId = is_string($value) && $value !== '' ? $value : null;

            // Sem mudança de loja ou sem loja atual (nenhum mapa para desvincular): nada a validar.
            if ($newStoreId === $planogram->store_id || ! $planogram->store_id) {
                return;
            }

            $hasLinkedGondolas = Gondola::query()
                ->where('planogram_id', $planogram->getKey())
                ->whereNotNull('linked_map_gondola_id')
                ->exists();

            if ($hasLinkedGondolas) {
                $fail(__('plannerate.map_region.store_change_blocked'));
            }
        };
    }
}
