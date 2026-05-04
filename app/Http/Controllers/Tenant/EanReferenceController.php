<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\StoreEanReferenceRequest;
use App\Http\Requests\Tenant\UpdateEanReferenceRequest;
use App\Models\EanReference;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EanReferenceController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EanReference::class);

        $search = $this->requestString($request, 'search');

        return $this->renderDeferredIndex('tenant/ean-references/Index', 'ean_references', fn (): LengthAwarePaginator => $this->eanReferencesPaginator(
            $search,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    private function eanReferencesPaginator(string $search, int $perPage): LengthAwarePaginator
    {
        return EanReference::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('ean', 'like', '%'.$search.'%')
                        ->orWhere('reference_description', 'like', '%'.$search.'%')
                        ->orWhere('brand', 'like', '%'.$search.'%')
                        ->orWhere('subbrand', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EanReference $eanReference): array => [
                'id' => $eanReference->id,
                'ean' => $eanReference->ean,
                'reference_description' => $eanReference->reference_description,
                'brand' => $eanReference->brand,
                'subbrand' => $eanReference->subbrand,
                'packaging_type' => $eanReference->packaging_type,
                'packaging_size' => $eanReference->packaging_size,
                'measurement_unit' => $eanReference->measurement_unit,
                'created_at' => $eanReference->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', EanReference::class);

        return Inertia::render('tenant/ean-references/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'ean_reference' => null,
        ]);
    }

    public function store(StoreEanReferenceRequest $request): RedirectResponse
    {
        $this->authorize('create', EanReference::class);

        EanReference::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.ean_references.messages.created'),
        ]);

        return to_route('tenant.ean-references.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, EanReference $eanReference): Response
    {
        unset($subdomain);
        $this->authorize('update', $eanReference);

        return Inertia::render('tenant/ean-references/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'ean_reference' => [
                'id' => $eanReference->id,
                'ean' => $eanReference->ean,
                'reference_description' => $eanReference->reference_description,
                'brand' => $eanReference->brand,
                'subbrand' => $eanReference->subbrand,
                'packaging_type' => $eanReference->packaging_type,
                'packaging_size' => $eanReference->packaging_size,
                'measurement_unit' => $eanReference->measurement_unit,
                'width' => $eanReference->width,
                'height' => $eanReference->height,
                'depth' => $eanReference->depth,
                'weight' => $eanReference->weight,
                'unit' => $eanReference->unit,
                'has_dimensions' => $eanReference->has_dimensions,
                'dimension_status' => $eanReference->dimension_status,
            ],
        ]);
    }

    public function update(UpdateEanReferenceRequest $request, string $subdomain, EanReference $eanReference): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $eanReference);

        $eanReference->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.ean_references.messages.updated'),
        ]);

        return to_route('tenant.ean-references.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, EanReference $eanReference): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $eanReference);

        $eanReference->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.ean_references.messages.deleted'),
        ]);

        return to_route('tenant.ean-references.index', $this->tenantRouteParameters());
    }
}
