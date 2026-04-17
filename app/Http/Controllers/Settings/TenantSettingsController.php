<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TenantUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantSettingsController extends Controller
{
    /**
     * Exibe a página de configurações do tenant.
     */
    public function edit(Request $request): Response
    {
        $tenant = app()->make('current.tenant');
        
        if (!$tenant) {
            abort(404, 'Tenant não encontrado.');
        }

        return Inertia::render('settings/TenantInfo', [
            'tenant' => $tenant->only([
                'id',
                'name',
                'slug',
                'subdomain',
                'domain',
                'email',
                'phone',
                'document',
                'logo',
                'description',
                'status',
            ]),
        ]);
    }

    /**
     * Atualiza as informações do tenant.
     */
    public function update(TenantUpdateRequest $request): RedirectResponse
    {
        $tenant = app()->make('current.tenant');
        
        if (!$tenant) {
            abort(404, 'Tenant não encontrado.');
        }

        $tenant->fill($request->validated());
        $tenant->save();

        return to_route('tenant-settings.edit')
            ->with('success', 'Informações do tenant atualizadas com sucesso.');
    }
}

