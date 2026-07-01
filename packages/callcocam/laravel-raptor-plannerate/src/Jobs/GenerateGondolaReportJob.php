<?php

namespace Callcocam\LaravelRaptorPlannerate\Jobs;

use App\Events\TenantNotificationBroadcast;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaCompraReportExport;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaDimensaoReportExport;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaImageReportExport;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaReportExport;
use Callcocam\LaravelRaptorPlannerate\Services\Reports\GondolaReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Gera um relatório de gôndola (Excel/PDF) em fila e notifica o usuário ao
 * concluir — via notificação em tela (broadcast/Reverb) e persistida (database),
 * ambas com link de download.
 *
 * TenantAware: o Spatie restaura o tenant corrente antes do handle(), então os
 * models tenant (Product, Gondola, etc.) resolvem a conexão correta. O tenantId
 * também é guardado como propriedade para uso no failed() (onde o tenant pode
 * não estar restaurado) e no preenchimento do tenant_id da notificação.
 */
class GenerateGondolaReportJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Geração pesada e não idempotente (nome com timestamp): sem retry automático. */
    public int $tries = 1;

    /** Folga para gôndolas grandes (a fila `default` tem timeout 660s). */
    public int $timeout = 600;

    /**
     * @param  string  $gondolaId  ULID da gôndola a relatar
     * @param  string  $format  excel|pdf|compra|dimensao|image
     * @param  string  $userId  ULID do usuário que solicitou (será notificado)
     * @param  string  $tenantId  ULID do tenant corrente no momento do dispatch
     */
    public function __construct(
        public string $gondolaId,
        public string $format,
        public string $userId,
        public string $tenantId,
    ) {
        $this->onQueue('default');
    }

    /**
     * Monta os dados, gera o arquivo, grava no disco `local` e notifica o usuário.
     */
    public function handle(GondolaReportService $service): void
    {
        // Tenant já restaurado (TenantAware) → models tenant funcionam.
        $data = $service->generateReportData($this->gondolaId);

        $slug = now()->format('d-m-Y-His');
        $dir = "reports/{$this->tenantId}";

        [$relativePath, $downloadName] = match ($this->format) {
            'pdf' => $this->storePdf($data, $dir, $slug),
            'compra' => $this->storeExcel(new GondolaCompraReportExport($data), $dir, "relatorio-compra-{$slug}.xlsx"),
            'dimensao' => $this->storeExcel(new GondolaDimensaoReportExport($data), $dir, "relatorio-dimensao-{$slug}.xlsx"),
            'image' => $this->storeExcel(new GondolaImageReportExport($data), $dir, "relatorio-imagem-{$slug}.xlsx"),
            default => $this->storeExcel(new GondolaReportExport($data), $dir, "relatorio-reposicao-{$slug}.xlsx"),
        };

        $this->notify(
            title: 'Relatório pronto',
            message: "Seu relatório ({$downloadName}) está disponível para download.",
            type: 'success',
            downloadUrl: $relativePath,
            downloadName: $downloadName,
        );
    }

    /**
     * Grava um Export xlsx no disco `local` e devolve [pathRelativo, nome].
     *
     * @return array{0: string, 1: string}
     */
    private function storeExcel(object $export, string $dir, string $name): array
    {
        $relative = "{$dir}/{$name}";
        Storage::disk('local')->makeDirectory($dir);
        $export->store(Storage::disk('local')->path($relative));

        return [$relative, $name];
    }

    /**
     * Gera o PDF (dompdf) a partir da view do relatório e grava no disco `local`.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}
     */
    private function storePdf(array $data, string $dir, string $slug): array
    {
        $name = "relatorio-reposicao-{$slug}.pdf";
        $relative = "{$dir}/{$name}";

        $pdf = Pdf::loadView('plannerate::gondola-report', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'dpi' => 150,
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        Storage::disk('local')->makeDirectory($dir);
        Storage::disk('local')->put($relative, $pdf->output());

        return [$relative, $name];
    }

    /**
     * Falha → notifica o usuário. O tenant pode não estar restaurado aqui, então
     * re-seleciona antes de gravar a notificação na conexão tenant correta.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('GenerateGondolaReportJob falhou', [
            'gondola_id' => $this->gondolaId,
            'format' => $this->format,
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
        ]);

        Tenant::query()->find($this->tenantId)?->makeCurrent();

        $this->notify(
            title: 'Falha ao gerar relatório',
            message: 'Não foi possível gerar o relatório. Tente novamente.',
            type: 'error',
        );
    }

    /**
     * Dispara a AppNotification (database + broadcast) para o usuário solicitante.
     *
     * Usa notifyNow() (envio SÍNCRONO) de propósito: a AppNotification é
     * ShouldQueue + NotTenantAware, então, se fosse re-enfileirada, rodaria num
     * job separado SEM tenant restaurado — e a conexão `tenant` (com database=null
     * fora de contexto tenant) não gravaria a notificação no banco do tenant.
     * Enviando aqui, dentro do handle() TenantAware, o tenant está corrente e a
     * notificação database cai no banco do tenant correto; o broadcast dispara no
     * mesmo contexto. O tenantId ainda é passado para preencher a coluna tenant_id
     * (via listener UpdateNotificationTenantId), essencial para o filtro de download.
     */
    private function notify(
        string $title,
        string $message,
        string $type,
        ?string $downloadUrl = null,
        ?string $downloadName = null,
    ): void {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $notification = new AppNotification(
            title: $title,
            message: $message,
            type: $type,
            downloadUrl: $downloadUrl,
            downloadName: $downloadName,
            tenantId: $this->tenantId,
        );

        // Persiste APENAS pelo canal database. Não usamos o canal broadcast
        // padrão: ele serializa o notifiable User (conexão tenant) e, ao rodar
        // no worker sem tenant restaurado, falha com ModelNotFoundException. O
        // notifyNow síncrono garante gravação no banco do tenant (corrente aqui)
        // e define $notification->id, reaproveitado no broadcast e no download.
        $user->notifyNow($notification, ['database']);

        // Broadcast em tempo real confiável: evento ShouldBroadcastNow com dados
        // primitivos, capturado pelo mesmo listener do front (useEchoNotification).
        try {
            TenantNotificationBroadcast::dispatch($this->userId, array_merge(
                $notification->toArray($user),
                [
                    'id' => $notification->id,
                    'type' => AppNotification::class,
                    'read_at' => null,
                ],
            ));
        } catch (\Throwable $e) {
            // A notificação já foi persistida (aparece ao recarregar); um problema
            // de broadcast (ex.: Reverb indisponível) não deve falhar o relatório.
            Log::warning('GenerateGondolaReportJob: falha no broadcast (notificação já persistida)', [
                'user_id' => $this->userId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
