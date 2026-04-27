<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
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
                'auth' => trans('auth'),
                'passwords' => trans('passwords'),
                'pagination' => trans('pagination'),
                'validation' => trans('validation'),
            ],
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $request->user(),
                'notifications' => fn (): ?array => $request->user()?->notifications()
                    ->latest()
                    ->take(15)
                    ->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'read_at' => $n->read_at,
                        'data' => $n->data,
                        'created_at' => $n->created_at->toISOString(),
                    ])
                    ->all(),
                'unread_count' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
            ],
            'tenant' => [
                'active_modules' => fn (): array => $this->resolveActiveTenantModules($request),
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

    private function resolveTenantFromContext(Request $request): ?Tenant
    {
        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Tenant) {
            return $routeTenant;
        }

        $current = Tenant::current();

        if ($current instanceof Tenant) {
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
