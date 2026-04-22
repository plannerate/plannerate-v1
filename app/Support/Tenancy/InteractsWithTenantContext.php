<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithTenantContext
{
    protected function tenantId(): ?string
    {
        $tenant = Tenant::current();

        if ($tenant !== null) {
            $key = $tenant->getKey();

            return $key === null ? null : (string) $key;
        }

        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (! app()->bound($containerKey)) {
            return null;
        }

        $resolved = app($containerKey);
        $key = $resolved?->getKey();

        return $key === null ? null : (string) $key;
    }

    protected function tenantSubdomain(): ?string
    {
        $route = request()->route();
        $fromRoute = $route?->parameter('subdomain');

        if (is_string($fromRoute) && $fromRoute !== '') {
            return $fromRoute;
        }

        return $this->subdomainFromHost();
    }

    private function subdomainFromHost(): ?string
    {
        $landlordDomain = strtolower((string) config('app.landlord_domain'));
        $host = strtolower(request()->getHost());

        if ($landlordDomain === '' || $host === '' || ! str_ends_with($host, '.'.$landlordDomain)) {
            return null;
        }

        $subdomain = substr($host, 0, -1 * (strlen($landlordDomain) + 1));

        return $subdomain === '' ? null : $subdomain;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function tenantRouteParameters(array $parameters = []): array
    {
        $subdomain = $this->tenantSubdomain();

        if ($subdomain === null) {
            return $parameters;
        }

        return ['subdomain' => $subdomain, ...$parameters];
    }

    protected function ensureBelongsToCurrentTenant(Model $model, string $column = 'tenant_id'): void
    {
        abort_if((string) data_get($model, $column) !== (string) $this->tenantId(), 404);
    }
}
