<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreEanReferenceRequest;
use App\Http\Requests\Landlord\UpdateEanReferenceRequest;
use App\Jobs\ProcessEanReferenceImageJob;
use App\Models\EanReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        $hasImageParam = $request->query('has_image', '');
        $hasImage = $hasImageParam === '1' ? true : ($hasImageParam === '0' ? false : null);

        return $this->renderDeferredIndex('landlord/ean-references/Index', 'ean_references', fn (): LengthAwarePaginator => $this->eanReferencesPaginator(
            $search,
            $sort,
            $direction,
            $this->resolvePerPage($request, 10),
            $hasImage,
        ), [
            'filters' => [
                'search' => $search,
                'has_image' => $hasImageParam,
            ],
            'can' => [
                'create' => Gate::allows('create', EanReference::class),
            ],
        ]);
    }

    private function eanReferencesPaginator(string $search, ?string $sort, string $direction, int $perPage, ?bool $hasImage = null): LengthAwarePaginator
    {
        return EanReference::query()
            ->when($hasImage === true, fn ($q) => $q->whereNotNull('image_front_url'))
            ->when($hasImage === false, fn ($q) => $q->whereNull('image_front_url'))
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
                'image_front_url' => $eanReference->image_url,
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

        EanReference::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.ean_references.messages.created'),
        ]);

        return $this->toLandlordRoute('landlord.ean-references.index');
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
                'dimension_status' => $eanReference->dimension_publish_status,
                'image_front_url' => $eanReference->image_front_url,
                'image_side_url' => $eanReference->image_side_url,
                'image_top_url' => $eanReference->image_top_url,
                'image_front_public_url' => $eanReference->image_front_url ? Storage::disk('public')->url($eanReference->image_front_url) : null,
                'image_side_public_url' => $eanReference->image_side_url ? Storage::disk('public')->url($eanReference->image_side_url) : null,
                'image_top_public_url' => $eanReference->image_top_url ? Storage::disk('public')->url($eanReference->image_top_url) : null,
            ],
        ]);
    }

    public function update(UpdateEanReferenceRequest $request, EanReference $eanReference): RedirectResponse
    {
        $this->authorize('update', $eanReference);

        $eanReference->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.ean_references.messages.updated'),
        ]);

        return $this->toLandlordRoute('landlord.ean-references.index');
    }

    public function fetchImage(string $eanReference): JsonResponse
    {
        $model = EanReference::on('landlord')->findOrFail($eanReference);

        $this->authorize('update', $model);

        ProcessEanReferenceImageJob::dispatch(
            eanReferenceId: (string) $model->id,
            force: true,
            notify: true,
            notifyUserId: (string) auth()->id(),
        );

        return response()->json([
            'message' => 'Processamento iniciado. A imagem será atualizada em breve.',
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $this->authorize('create', EanReference::class);

        $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $path = $request->file('file')->store('ean-references/uploads', 'public');

        return response()->json([
            'path' => $path,
            'public_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function destroy(EanReference $eanReference): RedirectResponse
    {
        $this->authorize('delete', $eanReference);

        $eanReference->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.ean_references.messages.deleted'),
        ]);

        return $this->toLandlordRoute('landlord.ean-references.index');
    }
}
