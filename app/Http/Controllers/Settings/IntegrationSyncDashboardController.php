<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\IntegrationSyncLog;
use App\Services\Sync\IntegrationSyncRetryService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IntegrationSyncDashboardController extends Controller
{
    public function __invoke(IntegrationSyncRetryService $retryService)
    {
        // Tenta pegar o cliente do usuário autenticado
        // Se não tiver, busca o cliente com logs de integração mais recentes
        $client = auth()->user()->client ?? 
                  Client::whereHas('integrationSyncLogs')->latest()->first() ??
                  Client::first();

        if (! $client) {
            return Inertia::render('settings/IntegrationSyncDashboard', [
                'stats' => [],
                'recentLogs' => [],
                'failedDays' => [],
                'message' => 'Nenhum cliente encontrado',
            ]);
        }

        // Estatísticas gerais por tipo de sync
        $stats = [
            'sales' => $this->getStatsForType($client, 'sales'),
            'products' => $this->getStatsForType($client, 'products'),
            'purchases' => $this->getStatsForType($client, 'purchases'),
        ];

        // Logs recentes (últimas 24h)
        $recentLogs = IntegrationSyncLog::where('client_id', $client->id)
            ->with(['client', 'store'])
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'sync_type' => $log->sync_type,
                'sync_date' => $log->sync_date,
                'status' => $log->status,
                'retry_count' => $log->retry_count,
                'consecutive_failures' => $log->consecutive_failures,
                'total_items' => $log->total_items,
                'error_message' => $log->error_message,
                'store_name' => $log->store->name ?? 'N/A',
                'created_at' => $log->created_at->format('d/m/Y H:i:s'),
            ]);

        // Dias com falhas que precisam retry
        $failedDays = IntegrationSyncLog::where('client_id', $client->id)
            ->whereIn('status', ['failed', 'skipped'])
            ->with('store')
            ->orderBy('sync_date', 'desc')
            ->orderBy('retry_count', 'desc')
            ->limit(30)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'sync_type' => $log->sync_type,
                'sync_date' => $log->sync_date,
                'status' => $log->status,
                'retry_count' => $log->retry_count,
                'consecutive_failures' => $log->consecutive_failures,
                'store_name' => $log->store->name ?? 'N/A',
                'error_message' => $log->error_message,
                'can_retry' => $log->canRetry(),
                'should_skip' => $log->shouldSkip(),
            ]);

        // Timeline de syncs (últimos 7 dias)
        $timeline = IntegrationSyncLog::where('client_id', $client->id)
            ->where('sync_date', '>=', now()->subDays(7)->format('Y-m-d'))
            ->select(
                'sync_date',
                'sync_type',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'success' then 1 else 0 end) as success_count"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed_count"),
                DB::raw("sum(case when status = 'skipped' then 1 else 0 end) as skipped_count")
            )
            ->groupBy('sync_date', 'sync_type')
            ->orderBy('sync_date', 'desc')
            ->get()
            ->groupBy('sync_date')
            ->map(fn ($logs) => [
                'date' => $logs->first()->sync_date,
                'sales' => $logs->firstWhere('sync_type', 'sales'),
                'products' => $logs->firstWhere('sync_type', 'products'),
                'purchases' => $logs->firstWhere('sync_type', 'purchases'),
            ])
            ->values();

        return Inertia::render('settings/IntegrationSyncDashboard', [
            'stats' => $stats,
            'recentLogs' => $recentLogs,
            'failedDays' => $failedDays,
            'timeline' => $timeline,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
            ],
        ]);
    }

    private function getStatsForType(Client $client, string $syncType): array
    {
        $logs = IntegrationSyncLog::where('client_id', $client->id)
            ->where('sync_type', $syncType)
            ->get();

        return [
            'total_days' => $logs->count(),
            'success' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'skipped' => $logs->where('status', 'skipped')->count(),
            'pending' => $logs->where('status', 'pending')->count(),
            'total_items' => $logs->sum('total_items'),
            'needs_retry' => $logs->where('status', 'failed')->where('retry_count', '<', 5)->count(),
            'consecutive_failures' => $logs->max('consecutive_failures') ?? 0,
        ];
    }
}
