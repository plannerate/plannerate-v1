<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithAddress;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\StoreStoreRequest;
use App\Http\Requests\Tenant\StoreUpdateRequest;
use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    use InteractsWithAddress;
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Store::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published']);

        return $this->renderDeferredIndex('tenant/stores/Index', 'stores', fn (): LengthAwarePaginator => $this->storesPaginator(
            $search,
            $status,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    private function storesPaginator(string $search, string $status, int $perPage): LengthAwarePaginator
    {
        return Store::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('document', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'code' => $store->code,
                'document' => $store->document,
                'status' => $store->status,
                'created_at' => $store->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Store::class);

        return Inertia::render('tenant/stores/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'store' => null,
            'address' => null,
        ]);
    }

    public function store(StoreStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Store::class);

        $validated = $request->validated();

        $store = Store::query()->create([
            ...Arr::except($validated, ['address', 'map']),
            ...$this->storeMapAttributes($validated['map'] ?? null),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        $this->syncAddress($store, is_array($validated['address'] ?? null) ? $validated['address'] : null, $request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.stores.messages.created'),
        ]);

        return to_route('tenant.stores.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Store $store): Response
    {
        unset($subdomain);
        $this->authorize('update', $store);

        $address = $store->addresses()->orderByDesc('is_default')->latest()->first();

        return Inertia::render('tenant/stores/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'document' => $store->document,
                'slug' => $store->slug,
                'code' => $store->code,
                'phone' => $store->phone,
                'email' => $store->email,
                'status' => $store->status,
                'description' => $store->description,
                'map' => $this->storeMapPayload($store),
            ],
            'address' => $address ? $this->addressPayload($address) : null,
        ]);
    }

    public function update(StoreUpdateRequest $request, string $subdomain, Store $store): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $store);

        $validated = $request->validated();

        $store->update([
            ...Arr::except($validated, ['address', 'map']),
            ...$this->storeMapAttributes($validated['map'] ?? null, $store->map_image_path),
        ]);

        $this->syncAddress($store, is_array($validated['address'] ?? null) ? $validated['address'] : null, $request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.stores.messages.updated'),
        ]);

        return to_route('tenant.stores.index', $this->tenantRouteParameters());
    }

    /**
     * @return array{image_url: string|null, regions: array<int, array<string, mixed>>}|null
     */
    private function storeMapPayload(Store $store): ?array
    {
        if (! $store->map_image_path && empty($store->map_regions)) {
            return null;
        }

        return [
            'image_url' => $store->map_image_path
                ? Storage::disk('public')->url($store->map_image_path)
                : null,
            'regions' => $store->map_regions ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function storeMapAttributes(mixed $map, ?string $currentImagePath = null): array
    {
        if (! is_array($map)) {
            return [];
        }

        $attributes = [];
        $image = $map['image'] ?? null;

        if (is_string($image) && $image !== '') {
            $attributes['map_image_path'] = $this->storeMapImage($image, $currentImagePath);
        }

        if (array_key_exists('regions', $map)) {
            $attributes['map_regions'] = $this->normalizeMapRegions($map['regions']);
        }

        return $attributes;
    }

    /**
     * @throws ValidationException
     */
    private function storeMapImage(string $image, ?string $currentImagePath = null): string
    {
        if (! preg_match('/^data:image\/(?<extension>png|jpe?g|webp);base64,(?<data>.+)$/', $image, $matches)) {
            throw ValidationException::withMessages([
                'map.image' => __('validation.image', ['attribute' => 'mapa da loja']),
            ]);
        }

        $contents = base64_decode($matches['data'], true);

        if ($contents === false) {
            throw ValidationException::withMessages([
                'map.image' => __('validation.image', ['attribute' => 'mapa da loja']),
            ]);
        }

        $extension = $matches['extension'] === 'jpeg' ? 'jpg' : $matches['extension'];
        $path = 'store-maps/'.Str::ulid().'.'.$extension;

        Storage::disk('public')->put($path, $contents);

        if ($currentImagePath) {
            Storage::disk('public')->delete($currentImagePath);
        }

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMapRegions(mixed $regions): array
    {
        if (is_string($regions)) {
            $regions = json_decode($regions, true);
        }

        if (! is_array($regions)) {
            return [];
        }

        return collect($regions)
            ->filter(fn (mixed $region): bool => is_array($region))
            ->map(fn (array $region): array => [
                'id' => (string) ($region['id'] ?? Str::ulid()),
                'x' => (int) round((float) ($region['x'] ?? 0)),
                'y' => (int) round((float) ($region['y'] ?? 0)),
                'width' => max(20, (int) round((float) ($region['width'] ?? 20))),
                'height' => max(20, (int) round((float) ($region['height'] ?? 20))),
                'shape' => in_array($region['shape'] ?? 'rectangle', ['rectangle', 'circle'], true)
                    ? $region['shape']
                    : 'rectangle',
                'label' => $this->nullableMapString($region['label'] ?? null),
                'type' => $this->nullableMapString($region['type'] ?? 'gondola') ?? 'gondola',
                'color' => $this->nullableMapString($region['color'] ?? null),
                'gondola_id' => $this->nullableMapString($region['gondola_id'] ?? null),
            ])
            ->values()
            ->all();
    }

    private function nullableMapString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function destroy(string $subdomain, Store $store): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $store);

        $store->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.stores.messages.deleted'),
        ]);

        return to_route('tenant.stores.index', $this->tenantRouteParameters());
    }
}
