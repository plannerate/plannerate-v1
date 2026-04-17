<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class LogViewerController extends Controller
{
    use AuthorizesRequests;

    private string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs/laravel.log');
    }

    /**
     * Exibe a página principal de logs
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'level', 'date_from', 'date_to']);
        $perPage = $request->get('per_page', 50);

        $logs = $this->getLogs($filters, $perPage);
        $logLevels = $this->getLogLevels();
        $logStats = $this->getLogStats();

        return Inertia::render('logs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'logLevels' => $logLevels,
            'logStats' => $logStats,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Busca logs com filtros aplicados
     */
    private function getLogs(array $filters, int $perPage): array
    {
        if (! File::exists($this->logPath)) {
            return [
                'data' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => 1,
                'last_page' => 1,
            ];
        }

        $content = File::get($this->logPath);
        $lines = array_filter(explode("\n", $content));

        // Parse dos logs
        $parsedLogs = $this->parseLogLines($lines);

        // Aplicar filtros
        $filteredLogs = $this->applyFilters($parsedLogs, $filters);

        // Ordenar por data (mais recente primeiro)
        usort($filteredLogs, function ($a, $b) {
            return strtotime($b['datetime']) - strtotime($a['datetime']);
        });

        // Paginação manual
        $total = count($filteredLogs);
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedLogs = array_slice($filteredLogs, $offset, $perPage);

        return [
            'data' => $paginatedLogs,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Parse das linhas do log em estrutura organizada
     */
    private function parseLogLines(array $lines): array
    {
        $logs = [];
        $currentLog = null;

        foreach ($lines as $line) {
            // Verifica se é início de um novo log entry
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/', $line, $matches)) {
                // Salva log anterior se existir
                if ($currentLog) {
                    $logs[] = $currentLog;
                }

                // Inicia novo log
                $currentLog = [
                    'datetime' => $matches[1],
                    'environment' => $matches[2],
                    'level' => strtoupper($matches[3]),
                    'message' => $matches[4],
                    'context' => '',
                    'formatted_date' => Carbon::parse($matches[1])->format('d/m/Y H:i:s'),
                    'level_color' => $this->getLevelColor($matches[3]),
                ];
            } elseif ($currentLog && ! empty(trim($line))) {
                // Adiciona linha de contexto ao log atual
                $currentLog['context'] .= $line."\n";
            }
        }

        // Adiciona último log
        if ($currentLog) {
            $logs[] = $currentLog;
        }

        return $logs;
    }

    /**
     * Aplica filtros aos logs
     */
    private function applyFilters(array $logs, array $filters): array
    {
        return array_filter($logs, function ($log) use ($filters) {
            // Filtro por busca
            if (! empty($filters['search'])) {
                $searchTerm = strtolower($filters['search']);
                $searchableText = strtolower($log['message'].' '.$log['context']);
                if (strpos($searchableText, $searchTerm) === false) {
                    return false;
                }
            }

            // Filtro por nível
            if (! empty($filters['level']) && $filters['level'] !== 'all') {
                if (strtolower($log['level']) !== strtolower($filters['level'])) {
                    return false;
                }
            }

            // Filtro por data de início
            if (! empty($filters['date_from'])) {
                $logDate = Carbon::parse($log['datetime']);
                $dateFrom = Carbon::parse($filters['date_from']);
                if ($logDate->lt($dateFrom)) {
                    return false;
                }
            }

            // Filtro por data de fim
            if (! empty($filters['date_to'])) {
                $logDate = Carbon::parse($log['datetime']);
                $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
                if ($logDate->gt($dateTo)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Retorna cor CSS baseada no nível do log
     */
    private function getLevelColor(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical', 'error' => 'red',
            'warning' => 'yellow',
            'notice', 'info' => 'blue',
            'debug' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Retorna níveis de log disponíveis
     */
    private function getLogLevels(): array
    {
        return [
            ['value' => 'all', 'label' => 'Todos os níveis'],
            ['value' => 'emergency', 'label' => 'Emergência'],
            ['value' => 'alert', 'label' => 'Alerta'],
            ['value' => 'critical', 'label' => 'Crítico'],
            ['value' => 'error', 'label' => 'Erro'],
            ['value' => 'warning', 'label' => 'Aviso'],
            ['value' => 'notice', 'label' => 'Notificação'],
            ['value' => 'info', 'label' => 'Informação'],
            ['value' => 'debug', 'label' => 'Debug'],
        ];
    }

    /**
     * Retorna estatísticas dos logs
     */
    private function getLogStats(): array
    {
        if (! File::exists($this->logPath)) {
            return [
                'total_entries' => 0,
                'file_size' => '0 KB',
                'last_modified' => null,
                'levels_count' => [],
            ];
        }

        $content = File::get($this->logPath);
        $lines = array_filter(explode("\n", $content));
        $parsedLogs = $this->parseLogLines($lines);

        // Conta logs por nível
        $levelsCount = [];
        foreach ($parsedLogs as $log) {
            $level = strtolower($log['level']);
            $levelsCount[$level] = ($levelsCount[$level] ?? 0) + 1;
        }

        return [
            'total_entries' => count($parsedLogs),
            'file_size' => $this->formatFileSize(File::size($this->logPath)),
            'last_modified' => Carbon::createFromTimestamp(File::lastModified($this->logPath))->format('d/m/Y H:i:s'),
            'levels_count' => $levelsCount,
        ];
    }

    /**
     * Formata tamanho do arquivo
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2).' '.$units[$power];
    }

    /**
     * Limpa o arquivo de log
     */
    public function clear()
    {
        try {
            if (File::exists($this->logPath)) {
                File::put($this->logPath, '');

                Log::info('Arquivo de log limpo pelo usuário', [
                    'user_id' => auth()->id(),
                    'user_email' => auth()->user()->email ?? 'N/A',
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Logs limpos com sucesso!',
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao limpar logs', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao limpar logs: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download do arquivo de log
     */
    public function download()
    {
        if (! File::exists($this->logPath)) {
            abort(404, 'Arquivo de log não encontrado');
        }

        return response()->download($this->logPath, 'laravel-'.date('Y-m-d-H-i-s').'.log');
    }
}
