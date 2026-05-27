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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Exporta as entradas filtradas do log selecionado como arquivo de texto para download.
     */
    public function download(Request $request, SystemLogService $systemLogService): StreamedResponse
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
        $entries = $systemLogService->readEntries($path, 5000);
        $filteredEntries = $systemLogService->filterEntries($entries, $search, $level, $keyOnly, $from, $to);

        $lines = array_map(static function (array $entry): string {
            return sprintf(
                '[%s] %s.%s: %s',
                $entry['timestamp'] ?? '',
                $entry['environment'] ?? '',
                strtoupper((string) ($entry['level'] ?? '')),
                $entry['message'] ?? '',
            );
        }, array_reverse($filteredEntries));

        $downloadName = sprintf(
            '%s-%s.log',
            pathinfo($selectedFile, PATHINFO_FILENAME),
            now()->format('Ymd-His'),
        );

        return response()->streamDownload(function () use ($lines): void {
            echo implode(PHP_EOL, $lines).PHP_EOL;
        }, $downloadName, [
            'Content-Type' => 'text/plain; charset=utf-8',
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

        return $this->toTenantRoute('tenant.system-logs.index', [
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
