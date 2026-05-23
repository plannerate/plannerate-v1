<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantSocialiteProvider;
use App\Support\Modules\TenantModuleService;
use App\Support\Navigation\SidebarNavigationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'translations' => fn (): array => [
                'app' => trans('app'),
                'plannerate' => trans('plannerate'),
                'auth' => trans('auth'),
                'passwords' => trans('passwords'),
                'pagination' => trans('pagination'),
                'validation' => trans('validation'),
                'planogram-templates' => trans('planogram-templates'),
                'site' => trans('site'),
            ],
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $request->user(),
                'notifications' => function () use ($request): ?array {
                    $user = $request->user();

                    if ($user === null) {
                        return null;
                    }

                    $tenantId = $this->resolveTenantFromContext($request)?->getKey();
                    $query = $user->notifications()->latest();

                    if (is_string($tenantId) && $tenantId !== '') {
                        $query->where('tenant_id', $tenantId);
                    } else {
                        $query->whereNull('tenant_id');
                    }

                    return $query
                        ->take(15)
                        ->get()
                        ->map(fn ($n) => [
                            'id' => $n->id,
                            'read_at' => $n->read_at,
                            'data' => $n->data,
                            'created_at' => $n->created_at->toISOString(),
                        ])
                        ->all();
                },
                'unread_count' => function () use ($request): int {
                    $user = $request->user();

                    if ($user === null) {
                        return 0;
                    }

                    $tenantId = $this->resolveTenantFromContext($request)?->getKey();
                    $query = $user->unreadNotifications();

                    if (is_string($tenantId) && $tenantId !== '') {
                        $query->where('tenant_id', $tenantId);
                    } else {
                        $query->whereNull('tenant_id');
                    }

                    return $query->count();
                },
            ],
            'tenant' => [
                'id' => fn (): ?string => $this->resolveTenantFromContext($request)?->getKey(),
                'name' => fn (): ?string => $this->resolveTenantFromContext($request)?->name,
                'slug' => fn (): ?string => $this->resolveTenantFromContext($request)?->slug,
                'active_modules' => fn (): array => $this->resolveActiveTenantModules($request),
                'socialite_providers' => fn (): array => $this->resolveActiveSocialiteProviders($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'navigation' => app(SidebarNavigationService::class)->build($request),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveActiveTenantModules(Request $request): array
    {
        $tenant = $this->resolveTenantFromContext($request);

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return app(TenantModuleService::class)->tenantActiveModuleSlugs($tenant);
    }

    /**
     * @return list<array{provider: string, label: string}>
     */
    private function resolveActiveSocialiteProviders(Request $request): array
    {
        $tenant = $this->resolveTenantFromContext($request);

        if (! $tenant instanceof Tenant) {
            return [];
        }

        $provider = $tenant->socialiteProvider;

        if (! $provider instanceof TenantSocialiteProvider || ! $provider->is_active) {
            return [];
        }

        return [['provider' => $provider->provider, 'label' => $provider->displayLabel()]];
    }

    private function resolveTenantFromContext(Request $request): ?Tenant
    {
        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Tenant) {

            config(['app.url' => $routeTenant->domain->host]);

            return $routeTenant;
        }

        $current = Tenant::current();

        if ($current instanceof Tenant) {
            config(['app.url' => $current->domain->host]);

            return $current;
        }

        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (! app()->bound($containerKey)) {
            return null;
        }

        $resolved = app($containerKey);

        return $resolved instanceof Tenant ? $resolved : null;
    }
}
