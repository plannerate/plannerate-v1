<?php

namespace App\Http\Middleware;

use Callcocam\LaravelRaptor\Models\Inspiration;
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
        $inspiration = Inspiration::inRandomOrder()->first();
        $message = $inspiration?->message ?? 'Organize com inteligência, venda com eficiência.';
        $author = $inspiration?->author ?? 'Plannerate';
        $tenant = app()->bound('current.tenant') ? app('current.tenant') : null;

        $navigation = [];

        // Add custom navigation items based on context
        if ($this->detectContext($request) === 'tenant' && $request->user()) {
            $navigation[] = [
                'title' => 'Integrações',
                'label' => 'Integrações',
                'href' => route('integrations.dashboard'),
                'icon' => 'Activity',
                'group' => 'Sistema',
                'order' => 10,
                'isActive' => $request->routeIs('integrations.dashboard'),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'subdomain' => $tenant->subdomain,
                'domain' => $tenant->domain,
            ] : null,
            'quote' => ['message' => $message, 'author' => $author],
            'auth' => [
                'user' => $request->user(),
                'user_id' => $request->user()?->id,
                'client_id' => config('app.current_domainable_id'),
                'store_id' => config('app.current_store_id'),
                'tenant_id' => config('app.current_tenant_id'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'navigation' => $navigation,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'cascading_options' => $request->session()->get('cascading_options'),
            'features' => [
                'auto_generate' => (bool) config('plannerate.features.auto_generate', false),
            ],
        ];
    }

    /**
     * Detecta o contexto (tenant ou landlord) baseado na URL
     */
    protected function detectContext(Request $request): string
    {
        $host = $request->getHost();

        if (str_contains($host, 'landlord.')) {
            return 'landlord';
        }

        return 'tenant';
    }
}
