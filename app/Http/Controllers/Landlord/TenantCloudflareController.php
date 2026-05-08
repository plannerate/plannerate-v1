<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Cloudflare\CloudflareService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TenantCloudflareController extends Controller
{
    public function __construct(
        private readonly CloudflareService $cloudflare,
    ) {}

    public function store(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->load('primaryDomain:id,tenant_id,host');
        $host = $tenant->primaryDomain?->host;
        $zoneId = config('cloudflare.zone_id', '');
        $cnameTarget = config('cloudflare.cname_target', '');

        if (! $this->cloudflare->isConfigured() || $zoneId === '') {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Cloudflare não está configurado. O registro DNS não será criado.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        if (! $host) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'O tenant não possui um host configurado.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        try {
            $result = $this->cloudflare->createRecord($zoneId, [
                'type' => 'CNAME',
                'name' => $host,
                'content' => $cnameTarget,
                'proxied' => true,
            ]);

            if (! ($result['success'] ?? false)) {
                $message = $result['errors'][0]['message'] ?? 'Erro ao criar registro DNS.';
                Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

                return to_route('landlord.tenants.edit', $tenant);
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro CNAME criado com sucesso.']);

            return to_route('landlord.tenants.edit', $tenant);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Não foi possível criar o registro DNS. Verifique a configuração do Cloudflare.']);

            return to_route('landlord.tenants.edit', $tenant);
        }
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->load('primaryDomain:id,tenant_id,host');
        $host = $tenant->primaryDomain?->host;
        $zoneId = config('cloudflare.zone_id', '');

        if (! $this->cloudflare->isConfigured() || $zoneId === '') {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Cloudflare não está configurado. O registro DNS não será removido.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        if (! $host) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'O tenant não possui um host configurado.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        try {
            $listResult = $this->cloudflare->listRecords($zoneId, 'CNAME', $host);
            $records = $listResult['result'] ?? [];
            $record = $records[0] ?? null;

            if (! $record) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => 'Nenhum registro DNS encontrado para este host.']);

                return to_route('landlord.tenants.edit', $tenant);
            }

            $deleteResult = $this->cloudflare->deleteRecord($zoneId, $record['id']);

            if (! ($deleteResult['success'] ?? false)) {
                $message = $deleteResult['errors'][0]['message'] ?? 'Erro ao remover registro DNS.';
                Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

                return to_route('landlord.tenants.edit', $tenant);
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro DNS removido com sucesso.']);

            return to_route('landlord.tenants.edit', $tenant);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Não foi possível remover o registro DNS. Verifique a configuração do Cloudflare.']);

            return to_route('landlord.tenants.edit', $tenant);
        }
    }
}
