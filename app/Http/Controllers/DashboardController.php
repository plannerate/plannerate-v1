<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\Auth\LoginAsTokenBroker;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use App\Models\Product;
use Callcocam\LaravelRaptorFlow\Services\Reports\FlowReportService;
use Callcocam\LaravelRaptorFlow\Services\Reports\Presets\OverviewFlowReportPreset;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected FlowReportService $flowReportService,
        protected LoginAsTokenBroker $loginAsTokenBroker
    ) {}

    /**
     * Display the dashboard.
     */
    public function index(Request $request): Response
    {
        $currentClientId = config('app.current_client_id');
        $tenantId = config('app.current_tenant_id');

        // Se for o tenant principal (sem client_id específico), busca os clients com domínios
        $clientsWithDomains = null;

        if (
            $currentClientId === null
            && $tenantId
            && $request->user()?->hasRole('super-admin')
        ) {
            $clientsWithDomains = Client::query()
                ->where('tenant_id', $tenantId)
                ->with('domain')
                ->whereHas('domain')
                ->where('status', 'published')
                ->orderBy('name')
                ->get()
                ->map(function ($client) use ($request, $tenantId) {
                    $domain = $client->domain;

                    // Usa o domínio configurado do client
                    $clientDomain = $domain->domain;

                    $token = $this->loginAsTokenBroker->issue(
                        actor: $request->user(),
                        tenantId: (string) $tenantId,
                        clientId: (string) $client->id
                    );

                    $accessUrl = $token
                        ? sprintf('%s://%s/login-as?token=%s', $request->getScheme(), $clientDomain, urlencode($token))
                        : '#';

                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                        'slug' => $client->slug,
                        'status' => $client->status,
                        'domain' => $domain ? [
                            'domain' => $clientDomain,
                            'url' => $accessUrl,
                            'is_primary' => $domain->is_primary ?? true,
                        ] : null,
                    ];
                });
        }

        // Estatísticas dos Planogramas
        $planogramStats = $this->getPlanogramStats($currentClientId);
        $workflowReportFilters = $this->extractWorkflowReportFilters($request);
        $workflowReport = $this->buildWorkflowReport($workflowReportFilters);

        return Inertia::render('Dashboard', [
            'clientsWithDomains' => $clientsWithDomains,
            'isTenantPrincipal' => $currentClientId === null,
            'planogramStats' => $planogramStats,
            'workflowReport' => $workflowReport,
        ]);
    }

    public function workflowReportData(Request $request): JsonResponse
    {
        $filters = $this->extractWorkflowReportFilters($request);

        return response()->json($this->buildWorkflowReport($filters));
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, mixed>
     */
    private function buildWorkflowReport(array $filters): array
    {
        return $this->flowReportService
            ->withPreset(OverviewFlowReportPreset::class)
            ->build($filters);
    }

    /**
     * Obtém estatísticas dos planogramas
     */
    private function getPlanogramStats(?string $currentClientId): array
    {
        $tenantId = config('app.current_tenant_id');

        // Se for tenant principal (sem client específico), não temos banco de tenant separado
        // Retorna estatísticas zeradas pois os planogramas estão nos bancos dos clients
        if (! $currentClientId) {
            return [
                'total_planograms' => 0,
                'total_gondolas' => 0,
                'total_products' => 0,
                'status_stats' => [
                    'draft' => 0,
                    'published' => 0,
                    'archived' => 0,
                ],
                'recent_planograms' => [],
                'top_categories' => [],
                'top_products_in_gondolas' => [],
                'planograms_by_month' => [],
                'card_trends' => [
                    'planograms' => [0, 0, 0, 0, 0, 0],
                    'gondolas' => [0, 0, 0, 0, 0, 0],
                    'products' => [0, 0, 0, 0, 0, 0],
                    'drafts' => [0, 0, 0, 0, 0, 0],
                ],
            ];
        }

        // Verifica se a conexão tenant está realmente disponível
        if (! config('database.connections.tenant')) {
            return [
                'total_planograms' => 0,
                'total_gondolas' => 0,
                'total_products' => 0,
                'status_stats' => [
                    'draft' => 0,
                    'published' => 0,
                    'archived' => 0,
                ],
                'recent_planograms' => [],
                'top_categories' => [],
                'top_products_in_gondolas' => [],
                'planograms_by_month' => [],
                'card_trends' => [
                    'planograms' => [0, 0, 0, 0, 0, 0],
                    'gondolas' => [0, 0, 0, 0, 0, 0],
                    'products' => [0, 0, 0, 0, 0, 0],
                    'drafts' => [0, 0, 0, 0, 0, 0],
                ],
            ];
        }

        $query = Planogram::query();

        // Filtra por tenant (sempre)
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Se não for tenant principal, filtra pelo client_id também
        if ($currentClientId) {
            $query->where('client_id', $currentClientId);
        }

        $totalPlanograms = $query->count();

        // Estatísticas por status
        $statusStats = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Total de gôndolas
        $gondolaQuery = Gondola::query();
        if ($tenantId) {
            $gondolaQuery->where('tenant_id', $tenantId);
        }
        // Gondolas não têm client_id, apenas tenant_id
        $totalGondolas = $gondolaQuery->count();

        // Total de produtos
        // No contexto tenant, não filtramos por client_id pois todos os produtos já pertencem ao tenant
        $productQuery = Product::query();
        if ($tenantId) {
            $productQuery->where('tenant_id', $tenantId);
        }
        // Só filtra por client_id se não estiver usando conexão tenant (banco separado)
        $productQuery->when($currentClientId, function ($query) use ($currentClientId) {
            $query->where('client_id', $currentClientId);
        });
        $totalProducts = $productQuery->count();

        // Últimos planogramas criados
        $recentPlanograms = (clone $query)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'client_name' => $p->client?->name,
                'store_name' => $p->store?->name,
                'created_at' => $p->created_at?->format('d/m/Y H:i'),
            ]);

        // Produtos por categoria (top 5)
        // No contexto tenant, não filtramos por client_id pois todos os produtos já pertencem ao tenant
        $categoryQuery = Product::query()
            ->select('categories.name', DB::raw('count(*) as count'))
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereNotNull('products.category_id');

        // Adiciona filtros de tenant em ambas as tabelas
        if ($tenantId) {
            $categoryQuery->where('products.tenant_id', $tenantId)
                ->where('categories.tenant_id', $tenantId);
        }

        // Só filtra por client_id se não estiver usando conexão tenant (banco separado)
        $categoryQuery->when($currentClientId, function ($query) use ($currentClientId) {
            $query->where('products.client_id', $currentClientId);
        });

        $topCategories = $categoryQuery
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'count' => $c->count,
            ]);

        // Produtos mais usados nas gôndolas (por quantidade em layers)
        $topProductsInGondolas = collect();
        if ($tenantId && \Schema::hasTable('layers')) {
            $topProductsInGondolas = DB::table('layers')
                ->whereNotNull('layers.product_id')
                ->where('layers.tenant_id', $tenantId)
                ->join('products', 'layers.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('sum(layers.quantity) as total'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'name' => $p->name,
                    'count' => (int) $p->total,
                ]);
        }

        // Planogramas criados nos últimos 6 meses (para gráfico)
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $driver = Planogram::query()->getConnection()->getDriverName();
        $monthExpression = $driver === 'pgsql'
            ? "to_char(created_at, 'YYYY-MM')"
            : "date_format(created_at, '%Y-%m')";
        $countsByMonth = Planogram::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($currentClientId, fn ($q) => $q->where('client_id', $currentClientId))
            ->where('created_at', '>=', $sixMonthsAgo)
            ->select(DB::raw("{$monthExpression} as month_key"), DB::raw('count(*) as count'))
            ->groupBy(DB::raw($monthExpression))
            ->orderBy(DB::raw($monthExpression))
            ->pluck('count', 'month_key')
            ->toArray();

        $planogramsByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->locale('pt_BR')->translatedFormat('M y');
            $planogramsByMonth[] = [
                'month' => $monthLabel,
                'count' => (int) ($countsByMonth[$monthKey] ?? 0),
                'key' => $monthKey,
            ];
        }

        // Tendências (últimos 6 meses) para os mini gráficos dos cards
        $planogramsTrend = array_column($planogramsByMonth, 'count');
        $gondolasTrend = $this->getCountByMonthTrend(Gondola::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)), $sixMonthsAgo, $driver, $monthExpression);
        $productsTrend = $this->getCountByMonthTrend(
            Product::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->when($currentClientId, fn ($q) => $q->where('client_id', $currentClientId)),
            $sixMonthsAgo,
            $driver,
            $monthExpression
        );
        $draftsTrend = $this->getCountByMonthTrend(
            Planogram::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->when($currentClientId, fn ($q) => $q->where('client_id', $currentClientId))
                ->where('status', 'draft'),
            $sixMonthsAgo,
            $driver,
            $monthExpression
        );

        return [
            'total_planograms' => $totalPlanograms,
            'total_gondolas' => $totalGondolas,
            'total_products' => $totalProducts,
            'status_stats' => [
                'draft' => $statusStats['draft'] ?? 0,
                'published' => $statusStats['published'] ?? 0,
                'archived' => $statusStats['archived'] ?? 0,
            ],
            'recent_planograms' => $recentPlanograms,
            'top_categories' => $topCategories,
            'top_products_in_gondolas' => $topProductsInGondolas,
            'planograms_by_month' => $planogramsByMonth,
            'card_trends' => [
                'planograms' => $planogramsTrend,
                'gondolas' => $gondolasTrend,
                'products' => $productsTrend,
                'drafts' => $draftsTrend,
            ],
        ];
    }

    /**
     * Retorna array com contagem por mês (últimos 6 meses) para um query builder.
     *
     * @return array<int>
     */
    private function getCountByMonthTrend($query, Carbon $sixMonthsAgo, string $driver, string $monthExpression): array
    {
        $countsByMonth = (clone $query)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->select(DB::raw("{$monthExpression} as month_key"), DB::raw('count(*) as count'))
            ->groupBy(DB::raw($monthExpression))
            ->orderBy(DB::raw($monthExpression))
            ->pluck('count', 'month_key')
            ->toArray();

        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthKey = Carbon::now()->subMonths($i)->format('Y-m');
            $trend[] = (int) ($countsByMonth[$monthKey] ?? 0);
        }

        return $trend;
    }

    /**
     * @return array<string, string|null>
     */
    private function extractWorkflowReportFilters(Request $request): array
    {
        return [
            'flow_slug' => $request->string('flow_slug')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'responsible_id' => $request->string('responsible_id')->toString() ?: null,
        ];
    }
}
