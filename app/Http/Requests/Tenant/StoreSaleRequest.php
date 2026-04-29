<?php

namespace App\Http\Requests\Tenant;

use App\Models\Sale;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Sale::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $salesTable = $this->tenantTable('sales');
        $storesTable = $this->tenantTable('stores');
        $productsTable = $this->tenantTable('products');

        return [
            'store_id' => ['required', 'ulid', Rule::exists($storesTable, 'id')->where('tenant_id', $tenantId)],
            'product_id' => ['nullable', 'ulid', Rule::exists($productsTable, 'id')->where('tenant_id', $tenantId)],
            'ean' => ['nullable', 'string', 'size:13'],
            'codigo_erp' => [
                'required',
                'string',
                'max:255',
                Rule::unique($salesTable, 'codigo_erp')
                    ->where('tenant_id', $tenantId)
                    ->where('store_id', (string) $this->input('store_id'))
                    ->where('sale_date', (string) $this->input('sale_date'))
                    ->where('promotion', (string) $this->input('promotion')),
            ],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'total_profit_margin' => ['nullable', 'numeric'],
            'sale_date' => ['required', 'date'],
            'promotion' => ['nullable', 'string', 'max:255'],
            'total_sale_quantity' => ['nullable', 'numeric', 'min:0'],
            'total_sale_value' => ['nullable', 'numeric', 'min:0'],
            'margem_contribuicao' => ['nullable', 'numeric'],
            'extra_data' => ['nullable', 'string'],
        ];
    }
}
