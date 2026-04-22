<?php

namespace App\Support\Navigation;

use Illuminate\Http\Request;

class SidebarNavigationService
{
    /**
     * Build sidebar navigation payload for the current request context.
     *
     * @return array{context: 'landlord'|'tenant', main: array<int, array{title: string, href: string, icon?: string, can: bool}>}
     */
    public function build(Request $request): array
    {
        $landlordDomain = (string) config('app.landlord_domain');
        $currentTenantContainerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');
        $hasCurrentTenant = app()->bound($currentTenantContainerKey) && app($currentTenantContainerKey) !== null;

        $isTenantContext = $hasCurrentTenant && strtolower($request->getHost()) !== strtolower($landlordDomain);

        return [
            'context' => $isTenantContext ? 'tenant' : 'landlord',
            'main' => $isTenantContext
                ? $this->tenantItems($request, $landlordDomain)
                : $this->landlordItems(),
        ];
    }

    /**
     * @return array<int, array{title: string, href: string, icon?: string, can: bool}>
     */
    private function landlordItems(): array
    {
        return [
            [
                'title' => __('app.navigation.dashboard'),
                'href' => route('dashboard', absolute: false),
                'icon' => 'layout-grid',
                'can' => true,
            ],
            [
                'title' => __('app.landlord.plans.navigation'),
                'href' => route('landlord.plans.index', absolute: false),
                'icon' => 'package-open',
                'can' => true,
            ],
            [
                'title' => __('app.landlord.tenants.navigation'),
                'href' => route('landlord.tenants.index', absolute: false),
                'icon' => 'building-2',
                'can' => true,
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, href: string, icon?: string, can: bool}>
     */
    private function tenantItems(Request $request, string $landlordDomain): array
    {
        $subdomain = $this->resolveSubdomain($request, $landlordDomain);

        $dashboardHref = $subdomain === null
            ? '/dashboard'
            : route('tenant.dashboard', ['subdomain' => $subdomain], false);

        return [
            [
                'title' => __('app.navigation.dashboard'),
                'href' => $dashboardHref,
                'icon' => 'layout-grid',
                'can' => true,
            ],
        ];
    }

    private function resolveSubdomain(Request $request, string $landlordDomain): ?string
    {
        $routeSubdomain = $request->route('subdomain');

        if (is_string($routeSubdomain) && $routeSubdomain !== '') {
            return $routeSubdomain;
        }

        $host = strtolower($request->getHost());
        $domain = strtolower($landlordDomain);

        if ($host === '' || $domain === '' || ! str_ends_with($host, '.'.$domain)) {
            return null;
        }

        $subdomain = substr($host, 0, -1 * (strlen($domain) + 1));

        return $subdomain === '' ? null : $subdomain;
    }
}
