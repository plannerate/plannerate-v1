<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ProviderStoreRequest;
use App\Http\Requests\Tenant\ProviderUpdateRequest;
use App\Models\Address;
use App\Models\Provider;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            'address' => null,
        ]);
    }

    public function store(ProviderStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Provider::class);

        $validated = $request->validated();

        $provider = Provider::query()->create([
            ...Arr::except($validated, ['address']),
            'tenant_id' => $this->tenantId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'is_default' => $request->boolean('is_default', true),
        ]);

        $this->syncAddress($provider, is_array($validated['address'] ?? null) ? $validated['address'] : null, $request);

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

        $address = $provider->addresses()->orderByDesc('is_default')->latest()->first();

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
            'address' => $address ? $this->addressPayload($address) : null,
        ]);
    }

    public function update(ProviderUpdateRequest $request, string $subdomain, Provider $provider): RedirectResponse
    {
        unset($subdomain);
        $this->ensureTenantOwnership($provider);
        $this->authorize('update', $provider);

        $validated = $request->validated();

        $provider->update([
            ...Arr::except($validated, ['address']),
            'is_default' => $request->boolean('is_default', true),
        ]);

        $this->syncAddress($provider, is_array($validated['address'] ?? null) ? $validated['address'] : null, $request);

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

    /**
     * @param  array<string, mixed>|null  $addressData
     */
    private function syncAddress(Provider $provider, ?array $addressData, Request $request): void
    {
        if ($addressData === null || ! $this->hasAddressData($addressData)) {
            return;
        }

        $address = null;
        $addressId = $addressData['id'] ?? null;

        if (is_string($addressId) && $addressId !== '') {
            $address = $provider->addresses()->whereKey($addressId)->first();
        }

        if (! $address instanceof Address) {
            $address = $provider->addresses()->orderByDesc('is_default')->latest()->first() ?? $provider->addresses()->make();
        }

        $address->fill([
            'type' => (string) ($addressData['type'] ?? 'home'),
            'tenant_id' => $this->tenantId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'name' => $addressData['name'] ?? null,
            'zip_code' => $addressData['zip_code'] ?? null,
            'street' => $addressData['street'] ?? null,
            'number' => $addressData['number'] ?? null,
            'complement' => $addressData['complement'] ?? null,
            'reference' => $addressData['reference'] ?? null,
            'additional_information' => $addressData['additional_information'] ?? null,
            'district' => $addressData['district'] ?? null,
            'city' => $addressData['city'] ?? null,
            'country' => $addressData['country'] ?? 'Brasil',
            'state' => $addressData['state'] ?? null,
            'is_default' => (bool) ($addressData['is_default'] ?? false),
            'status' => (string) ($addressData['status'] ?? 'draft'),
        ]);

        $provider->addresses()->save($address);
    }

    /**
     * @param  array<string, mixed>  $addressData
     */
    private function hasAddressData(array $addressData): bool
    {
        return collect($addressData)
            ->except(['id', 'is_default', 'status', 'country'])
            ->contains(fn ($value): bool => is_string($value) && trim($value) !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(Address $address): array
    {
        return [
            'id' => $address->id,
            'type' => $address->type,
            'name' => $address->name,
            'zip_code' => $address->zip_code,
            'street' => $address->street,
            'number' => $address->number,
            'complement' => $address->complement,
            'reference' => $address->reference,
            'additional_information' => $address->additional_information,
            'district' => $address->district,
            'city' => $address->city,
            'country' => $address->country,
            'state' => $address->state,
            'is_default' => (bool) $address->is_default,
            'status' => $address->status,
        ];
    }
}
