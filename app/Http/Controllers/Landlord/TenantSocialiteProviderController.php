<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSocialiteProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantSocialiteProviderController extends Controller
{
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(['google', 'azure'])],
            'label' => ['nullable', 'string', 'max:100'],
            'client_id' => ['required', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'azure_tenant' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $existing = $tenant->socialiteProvider;

        if (empty($data['client_secret']) && $existing instanceof TenantSocialiteProvider) {
            unset($data['client_secret']);
        }

        TenantSocialiteProvider::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $data,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.socialite_providers.messages.updated'),
        ]);

        return $this->toLandlordRoute('landlord.tenants.index');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->socialiteProvider?->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.socialite_providers.messages.deleted'),
        ]);

        return $this->toLandlordRoute('landlord.tenants.index');
    }
}
