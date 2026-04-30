<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Logs\SystemLogService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request, SystemLogService $systemLogService): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = trim((string) $request->query('search', ''));
        $level = trim(strtolower((string) $request->query('level', '')));
        $keyOnly = $request->boolean('key_only');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $path = storage_path('logs/laravel.log');
        $entries = $systemLogService->readEntries($path, 500);
        $filteredEntries = $systemLogService->filterEntries($entries, $search, $level, $keyOnly, $from, $to);

        return Inertia::render('tenant/system-logs/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'entries' => $filteredEntries,
            'filters' => [
                'search' => $search,
                'level' => $level,
                'key_only' => $keyOnly,
                'from' => $from,
                'to' => $to,
            ],
            'summary' => [
                'total' => count($entries),
                'filtered' => count($filteredEntries),
            ],
            'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
        ]);
    }

    public function clear(): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        app(SystemLogService::class)->clear(storage_path('logs/laravel.log'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Logs limpos com sucesso.',
        ]);

        return to_route('tenant.system-logs.index', $this->tenantRouteParameters());
    }
}
