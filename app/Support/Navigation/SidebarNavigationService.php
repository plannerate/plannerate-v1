<?php

namespace App\Support\Navigation;

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Planogram;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
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
            ->item('landlord.plans', function ($item): void {
                $item
                    ->label(__('app.landlord.plans.navigation'))
                    ->href(route('landlord.plans.index', absolute: false))
                    ->icon('package-open')
                    ->authorize('viewAny', Plan::class)
                    ->setOrder(20);
            })
            ->item('landlord.modules', function ($item): void {
                $item
                    ->label(__('app.landlord.modules.navigation'))
                    ->href(route('landlord.modules.index', absolute: false))
                    ->icon('blocks')
                    ->authorize('viewAny', Module::class)
                    ->setOrder(30);
            })
            ->item('landlord.tenants', function ($item): void {
                $item
                    ->label(__('app.landlord.tenants.navigation'))
                    ->href(route('landlord.tenants.index', absolute: false))
                    ->icon('building-2')
                    ->authorize('viewAny', Tenant::class)
                    ->setOrder(40);
            })
            ->item('landlord.permissions', function ($item): void {
                $item
                    ->label(__('app.landlord.permissions.navigation'))
                    ->href(route('landlord.permissions.index', absolute: false))
                    ->icon('key-round')
                    ->authorize('viewAny', Permission::class)
                    ->setOrder(50);
            })
            ->item('landlord.roles', function ($item): void {
                $item
                    ->label(__('app.landlord.roles.navigation'))
                    ->href(route('landlord.roles.index', absolute: false))
                    ->icon('shield-check')
                    ->authorize('viewAny', Role::class)
                    ->setOrder(60);
            })
            ->item('landlord.users', function ($item): void {
                $item
                    ->label(__('app.landlord.users.navigation'))
                    ->href(route('landlord.users.index', absolute: false))
                    ->icon('users')
                    ->authorize('viewAny', User::class)
                    ->setOrder(70);
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
            })
            ->item('tenant.categories', function ($item) use ($subdomain): void {
                $item
                    ->label(__('app.tenant.categories.navigation'))
                    ->href(route('tenant.categories.index', ['subdomain' => $subdomain], false))
                    ->icon('folder-tree')
                    ->authorize('viewAny', Category::class)
                    ->setOrder(20);
            })
            ->item('tenant.products', function ($item) use ($subdomain): void {
                $item
                    ->label(__('app.tenant.products.navigation'))
                    ->href(route('tenant.products.index', ['subdomain' => $subdomain], false))
                    ->icon('package')
                    ->authorize('viewAny', Product::class)
                    ->setOrder(30);
            })
            ->item('tenant.stores', function ($item) use ($subdomain): void {
                $item
                    ->label(__('app.tenant.stores.navigation'))
                    ->href(route('tenant.stores.index', ['subdomain' => $subdomain], false))
                    ->icon('store')
                    ->authorize('viewAny', Store::class)
                    ->setOrder(40);
            })
            ->item('tenant.clusters', function ($item) use ($subdomain): void {
                $item
                    ->label(__('app.tenant.clusters.navigation'))
                    ->href(route('tenant.clusters.index', ['subdomain' => $subdomain], false))
                    ->icon('blocks')
                    ->authorize('viewAny', Cluster::class)
                    ->setOrder(50);
            })
            ->item('tenant.providers', function ($item) use ($subdomain): void {
                $item
                    ->label(__('app.tenant.providers.navigation'))
                    ->href(route('tenant.providers.index', ['subdomain' => $subdomain], false))
                    ->icon('truck')
                    ->authorize('viewAny', Provider::class)
                    ->setOrder(60);
            })
            ->item('tenant.planograms', function ($item) use ($subdomain): void {
                $item
                    ->label(__('app.tenant.planograms.navigation'))
                    ->href(route('tenant.planograms.index', ['subdomain' => $subdomain], false))
                    ->icon('layout-template')
                    ->authorize('viewAny', Planogram::class)
                    ->setOrder(70);
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
