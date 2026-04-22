<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;

trait InteractsWithTenantContext
{
    protected function tenantId(): ?string
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (! app()->bound($containerKey)) {
            return null;
        }

        return app($containerKey)?->getKey();
    }

    protected function tenantSubdomain(): ?string
    {
        $subdomain = request()->route('subdomain');

        if (! is_string($subdomain) || $subdomain === '') {
            return null;
        }

        return $subdomain;
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
