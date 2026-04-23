<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStoreRequest;
use App\Http\Requests\Tenant\StoreUpdateRequest;
use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Store::class);

        $tenantId = $this->tenantId();
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $hasStatusFilter = in_array($status, ['draft', 'published'], true);

        $stores = Store::query()
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('document', 'like', '%'.$search.'%');
                });
            })
            ->when($hasStatusFilter, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
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

        return Inertia::render('tenant/stores/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'stores' => $stores,
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Store::class);

        return Inertia::render('tenant/stores/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'store' => null,
        ]);
    }

    public function store(StoreStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Store::class);

        Store::query()->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.stores.messages.created'),
        ]);

        return to_route('tenant.stores.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Store $store): Response
    {
        unset($subdomain);
        $this->ensureTenantOwnership($store);
        $this->authorize('update', $store);

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
            ],
        ]);
    }

    public function update(StoreUpdateRequest $request, string $subdomain, Store $store): RedirectResponse
    {
        unset($subdomain);
        $this->ensureTenantOwnership($store);
        $this->authorize('update', $store);

        $store->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.stores.messages.updated'),
        ]);

        return to_route('tenant.stores.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, Store $store): RedirectResponse
    {
        unset($subdomain);
        $this->ensureTenantOwnership($store);
        $this->authorize('delete', $store);

        $store->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.stores.messages.deleted'),
        ]);

        return to_route('tenant.stores.index', $this->tenantRouteParameters());
    }

    private function ensureTenantOwnership(Store $store): void
    {
        $this->ensureBelongsToCurrentTenant($store);
    }
}
