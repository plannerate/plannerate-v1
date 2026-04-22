<?php

namespace App\Support\Navigation;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\Navigation\Menu\Menu;
use App\Support\Navigation\Menu\MenuPayloadAdapter;
use Illuminate\Http\Request;

class SidebarNavigationService
{
    public function __construct(
        private MenuPayloadAdapter $menuPayloadAdapter,
    ) {}

    /**
     * @return array{context: string, main: array<int, array<string, mixed>>}
     */
    public function build(Request $request): array
    {
        $menu = $this->resolveContextMenu($request);

        return $this->menuPayloadAdapter->toNavigation($menu, $request->user());
    }

    private function resolveContextMenu(Request $request): Menu
    {
        $landlordDomain = (string) config('app.landlord_domain');
        $currentTenantContainerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');
        $hasCurrentTenant = app()->bound($currentTenantContainerKey) && app($currentTenantContainerKey) !== null;
        $isTenantContext = $hasCurrentTenant && strtolower($request->getHost()) !== strtolower($landlordDomain);

        return $isTenantContext
            ? $this->tenantMenu($request, $landlordDomain)
            : $this->landlordMenu();
    }

    private function landlordMenu(): Menu
    {
        return Menu::make('landlord')
            ->item('landlord.dashboard', function ($item): void {
                $item
                    ->label(__('app.navigation.dashboard'))
                    ->href(route('dashboard', absolute: false))
                    ->icon('layout-grid')
                    ->authorize('viewAny', Tenant::class)
                    ->setOrder(10);
            })
            ->submenu('landlord.registries', function ($submenu): void {
                $submenu
                    ->label(__('app.landlord.common.registries'))
                    ->icon('folder-kanban')
                    ->authorize('viewAny', Tenant::class)
                    ->setOrder(30)
                    ->item('landlord.plans', function ($item): void {
                        $item
                            ->label(__('app.landlord.plans.navigation'))
                            ->href(route('landlord.plans.index', absolute: false))
                            ->icon('package-open')
                            ->authorize('viewAny', Plan::class)
                            ->setOrder(10);
                    })
                    ->item('landlord.tenants', function ($item): void {
                        $item
                            ->label(__('app.landlord.tenants.navigation'))
                            ->href(route('landlord.tenants.index', absolute: false))
                            ->icon('building-2')
                            ->authorize('viewAny', Tenant::class)
                            ->setOrder(20);
                    })
                    ->item('landlord.roles', function ($item): void {
                        $item
                            ->label(__('app.landlord.roles.navigation'))
                            ->href(route('landlord.roles.index', absolute: false))
                            ->icon('shield-check')
                            ->authorize('viewAny', Role::class)
                            ->setOrder(30);
                    });
            });
    }

    private function tenantMenu(Request $request, string $landlordDomain): Menu
    {
        $subdomain = $this->resolveSubdomain($request, $landlordDomain);

        $dashboardHref = $subdomain === null
            ? '/dashboard'
            : route('tenant.dashboard', ['subdomain' => $subdomain], false);

        return Menu::make('tenant')
            ->item('tenant.dashboard', function ($item) use ($dashboardHref): void {
                $item
                    ->label(__('app.navigation.dashboard'))
                    ->href($dashboardHref)
                    ->icon('layout-grid')
                    ->authorize('viewAny', Tenant::class)
                    ->setOrder(10);
            });
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
