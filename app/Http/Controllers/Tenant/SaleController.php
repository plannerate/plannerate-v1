<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSaleRequest;
use App\Http\Requests\Tenant\UpdateSaleRequest;
use App\Models\Sale;
use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $search = trim((string) $request->string('search'));
        $storeId = trim((string) $request->string('store_id'));
        $hasStoreFilter = $storeId !== '';
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['codigo_erp', 'store', 'sale_date', 'total_sale_quantity', 'total_sale_value'], true)
            ? $requestedSort
            : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'desc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'desc';

        $sales = Sale::query()
            ->with(['store:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('codigo_erp', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%')
                        ->orWhere('promotion', 'like', '%'.$search.'%');
                });
            })
            ->when($hasStoreFilter, fn ($query) => $query->where('store_id', $storeId))
            ->when(
                $sort !== null,
                function ($query) use ($sort, $direction): void {
                    if ($sort === 'store') {
                        $query->orderBy(
                            Store::query()
                                ->select('name')
                                ->whereColumn('stores.id', 'sales.store_id')
                                ->limit(1),
                            $direction,
                        );

                        return;
                    }

                    $query->orderBy($sort, $direction);
                },
                fn ($query) => $query->latest('sale_date'),
            )
            ->paginate($this->resolvePerPage($request, 10))
            ->withQueryString()
            ->through(fn (Sale $sale): array => [
                'id' => $sale->id,
                'store' => $sale->store?->name,
                'ean' => $sale->ean,
                'codigo_erp' => $sale->codigo_erp,
                'sale_date' => $sale->sale_date?->toDateString(),
                'promotion' => $sale->promotion,
                'total_sale_quantity' => $sale->total_sale_quantity,
                'total_sale_value' => $sale->total_sale_value,
            ]);

        return Inertia::render('tenant/sales/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'sales' => $sales,
            'filters' => [
                'search' => $search,
                'store_id' => $hasStoreFilter ? $storeId : '',
            ],
            'filter_options' => [
                'stores' => $this->storesForSelect(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Sale::class);

        return Inertia::render('tenant/sales/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'sale' => null,
            'stores' => $this->storesForSelect(),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        Sale::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.sales.messages.created'),
        ]);

        return to_route('tenant.sales.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Sale $sale): Response
    {
        unset($subdomain);
        $this->authorize('update', $sale);

        return Inertia::render('tenant/sales/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'sale' => [
                'id' => $sale->id,
                'store_id' => $sale->store_id,
                'product_id' => $sale->product_id,
                'ean' => $sale->ean,
                'codigo_erp' => $sale->codigo_erp,
                'acquisition_cost' => $sale->acquisition_cost,
                'sale_price' => $sale->sale_price,
                'total_profit_margin' => $sale->total_profit_margin,
                'sale_date' => $sale->sale_date?->toDateString(),
                'promotion' => $sale->promotion,
                'total_sale_quantity' => $sale->total_sale_quantity,
                'total_sale_value' => $sale->total_sale_value,
                'margem_contribuicao' => $sale->margem_contribuicao,
                'extra_data' => $sale->extra_data,
            ],
            'stores' => $this->storesForSelect(),
        ]);
    }

    public function update(UpdateSaleRequest $request, string $subdomain, Sale $sale): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $sale);

        $sale->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.sales.messages.updated'),
        ]);

        return to_route('tenant.sales.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, Sale $sale): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $sale);

        $sale->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.sales.messages.deleted'),
        ]);

        return to_route('tenant.sales.index', $this->tenantRouteParameters());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function storesForSelect(): array
    {
        return Store::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
            ])
            ->all();
    }
}
