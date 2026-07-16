<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Expõe métricas operacionais da aplicação no formato texto do Prometheus.
 * É de nível de processo/fila — não acessa dados de nenhum tenant. Protegido
 * por VerifyMetricsToken e raspado pelo Prometheus da VPS via Traefik.
 *
 * Cada métrica degrada para 0 se sua origem (Redis/Horizon/DB) estiver
 * indisponível, garantindo resposta 200 estável para o scraper.
 */
class MetricsController extends Controller
{
    /**
     * Filas Horizon monitoradas (ver config/horizon.php).
     *
     * @var list<string>
     */
    private const QUEUES = ['critical', 'default', 'imports-fetch', 'imports-process', 'maintenance'];

    public function __invoke(Request $request): Response
    {
        $lines = [
            '# HELP plannerate_up Endpoint de métricas está respondendo.',
            '# TYPE plannerate_up gauge',
            'plannerate_up 1',
            '# HELP plannerate_horizon_queue_pending Jobs aguardando processamento por fila.',
            '# TYPE plannerate_horizon_queue_pending gauge',
        ];

        foreach (self::QUEUES as $queue) {
            $lines[] = sprintf('plannerate_horizon_queue_pending{queue="%s"} %d', $queue, $this->queueSize($queue));
        }

        $lines[] = '# HELP plannerate_horizon_failed_jobs_total Total de jobs na tabela failed_jobs.';
        $lines[] = '# TYPE plannerate_horizon_failed_jobs_total gauge';
        $lines[] = 'plannerate_horizon_failed_jobs_total '.$this->failedJobs();

        $lines[] = '# HELP plannerate_horizon_up Horizon possui master supervisor ativo (1) ou não (0).';
        $lines[] = '# TYPE plannerate_horizon_up gauge';
        $lines[] = 'plannerate_horizon_up '.$this->horizonUp();

        return response(implode("\n", $lines)."\n", Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function queueSize(string $queue): int
    {
        try {
            return (int) Queue::connection('redis')->size($queue);
        } catch (Throwable) {
            return 0;
        }
    }

    private function failedJobs(): int
    {
        try {
            return (int) DB::connection(config('queue.failed.database'))
                ->table('failed_jobs')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function horizonUp(): int
    {
        try {
            return count(app(MasterSupervisorRepository::class)->all()) > 0 ? 1 : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
