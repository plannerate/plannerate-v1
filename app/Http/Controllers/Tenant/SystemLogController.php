<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Logs\SystemLogService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $selectedFile = trim((string) $request->query('file', 'laravel.log'));

        $logFiles = $this->availableLogFiles();
        if (! in_array($selectedFile, $logFiles, true)) {
            $selectedFile = 'laravel.log';
        }

        $path = storage_path('logs/'.$selectedFile);
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
                'file' => $selectedFile,
            ],
            'summary' => [
                'total' => count($entries),
                'filtered' => count($filteredEntries),
            ],
            'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
            'files' => $logFiles,
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        $selectedFile = trim((string) $request->query('file', 'laravel.log'));
        $logFiles = $this->availableLogFiles();
        if (! in_array($selectedFile, $logFiles, true)) {
            $selectedFile = 'laravel.log';
        }

        app(SystemLogService::class)->clear(storage_path('logs/'.$selectedFile));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Log "%s" limpo com sucesso.', $selectedFile),
        ]);

        return to_route('tenant.system-logs.index', [
            ...$this->tenantRouteParameters(),
            'file' => $selectedFile,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function availableLogFiles(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];

        $normalized = collect($files)
            ->filter(static fn ($file): bool => is_string($file) && is_file($file))
            ->map(static fn (string $file): string => basename($file))
            ->filter(static fn (string $file): bool => Str::endsWith($file, '.log'))
            ->values()
            ->all();

        if (! in_array('laravel.log', $normalized, true)) {
            $normalized[] = 'laravel.log';
        }

        $archives = array_values(array_filter(
            $normalized,
            static fn (string $file): bool => $file !== 'laravel.log',
        ));

        rsort($archives);

        return ['laravel.log', ...$archives];
    }
}
