<?php

namespace App\Multitenancy\TenantFinder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class DomainTenantWithDomainsFinder extends TenantFinder
{
    /**
     * Find the current tenant for the incoming request host.
     */
    public function findForRequest(Request $request): ?IsTenant
    {
        $tenantModel = config('multitenancy.tenant_model');
        $host = strtolower($request->getHost());

        if (! is_string($tenantModel) || $tenantModel === '' || $host === '') {
            return null;
        }

        /** @var class-string<Model&IsTenant> $tenantModel */
        return $tenantModel::query()
            ->whereHas('domains', function ($query) use ($host): void {
                $query
                    ->where('host', $host)
                    ->where('type', 'subdomain')
                    ->where('is_active', true);
            })
            ->first();
    }
}
