<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ProviderStoreRequest;
use App\Http\Requests\Tenant\ProviderUpdateRequest;
use App\Models\Provider;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Provider::class);

        $tenantId = $this->tenantId();
        $search = trim((string) $request->string('search'));
        $isDefault = trim((string) $request->string('is_default'));
        $hasDefaultFilter = in_array($isDefault, ['0', '1'], true);

        $providers = Provider::query()
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('cnpj', 'like', '%'.$search.'%');
                });
            })
            ->when($hasDefaultFilter, fn ($query) => $query->where('is_default', $isDefault === '1'))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Provider $provider): array => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'email' => $provider->email,
                'phone' => $provider->phone,
                'cnpj' => $provider->cnpj,
                'is_default' => (bool) $provider->is_default,
                'created_at' => $provider->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('tenant/providers/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'providers' => $providers,
            'filters' => [
                'search' => $search,
                'is_default' => $hasDefaultFilter ? $isDefault : '',
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Provider::class);

        return Inertia::render('tenant/providers/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'provider' => null,
        ]);
    }

    public function store(ProviderStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Provider::class);

        Provider::query()->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'is_default' => $request->boolean('is_default', true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.providers.messages.created'),
        ]);

        return to_route('tenant.providers.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Provider $provider): Response
    {
        unset($subdomain);
        $this->ensureTenantOwnership($provider);
        $this->authorize('update', $provider);

        return Inertia::render('tenant/providers/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'provider' => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'email' => $provider->email,
                'phone' => $provider->phone,
                'cnpj' => $provider->cnpj,
                'is_default' => (bool) $provider->is_default,
                'description' => $provider->description,
            ],
        ]);
    }

    public function update(ProviderUpdateRequest $request, string $subdomain, Provider $provider): RedirectResponse
    {
        unset($subdomain);
        $this->ensureTenantOwnership($provider);
        $this->authorize('update', $provider);

        $provider->update([
            ...$request->validated(),
            'is_default' => $request->boolean('is_default', true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.providers.messages.updated'),
        ]);

        return to_route('tenant.providers.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, Provider $provider): RedirectResponse
    {
        unset($subdomain);
        $this->ensureTenantOwnership($provider);
        $this->authorize('delete', $provider);

        $provider->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.providers.messages.deleted'),
        ]);

        return to_route('tenant.providers.index', $this->tenantRouteParameters());
    }

    private function ensureTenantOwnership(Provider $provider): void
    {
        $this->ensureBelongsToCurrentTenant($provider);
    }
}
