<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreEanReferenceRequest;
use App\Http\Requests\Landlord\UpdateEanReferenceRequest;
use App\Models\EanReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EanReferenceController extends Controller
{
    use InteractsWithDeferredIndex;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EanReference::class);

        $search = $this->requestString($request, 'search');
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['ean', 'reference_description', 'brand', 'packaging_type', 'width', 'created_at'], true)
            ? $requestedSort
            : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'asc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'asc';

        return $this->renderDeferredIndex('landlord/ean-references/Index', 'ean_references', fn (): LengthAwarePaginator => $this->eanReferencesPaginator(
            $search,
            $sort,
            $direction,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    private function eanReferencesPaginator(string $search, ?string $sort, string $direction, int $perPage): LengthAwarePaginator
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
            ->when(
                $sort !== null,
                fn ($query) => $query->orderBy($sort, $direction),
                fn ($query) => $query->latest(),
            )
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
                'width' => $eanReference->width,
                'height' => $eanReference->height,
                'depth' => $eanReference->depth,
                'weight' => $eanReference->weight,
                'unit' => $eanReference->unit,
                'created_at' => $eanReference->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', EanReference::class);

        return Inertia::render('landlord/ean-references/Form', [
            'ean_reference' => null,
        ]);
    }

    public function store(StoreEanReferenceRequest $request): RedirectResponse
    {
        $this->authorize('create', EanReference::class);

        Log::warning('Landlord EanReferenceController store called', [
            'route' => request()->route()?->getName(),
            'connection' => 'landlord',
            'ean' => $request->input('ean'),
        ]);

        EanReference::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.ean_references.messages.created'),
        ]);

        return to_route('landlord.ean-references.index');
    }

    public function edit(EanReference $eanReference): Response
    {
        $this->authorize('update', $eanReference);

        return Inertia::render('landlord/ean-references/Form', [
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

    public function update(UpdateEanReferenceRequest $request, EanReference $eanReference): RedirectResponse
    {
        $this->authorize('update', $eanReference);

        Log::warning('Landlord EanReferenceController update called', [
            'route' => request()->route()?->getName(),
            'connection' => 'landlord',
            'ean_reference_id' => $eanReference->id,
            'ean' => $request->input('ean'),
        ]);

        $eanReference->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.ean_references.messages.updated'),
        ]);

        return to_route('landlord.ean-references.index');
    }

    public function destroy(EanReference $eanReference): RedirectResponse
    {
        $this->authorize('delete', $eanReference);

        $eanReference->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.ean_references.messages.deleted'),
        ]);

        return to_route('landlord.ean-references.index');
    }
}
