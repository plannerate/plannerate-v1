# Relatórios de Gôndola em Fila (Queued Reports)

Guia de implementação para mover a geração dos relatórios de gôndola (Excel/PDF)
para **jobs em fila**, notificando o usuário quando o arquivo estiver pronto —
via **notificação em tela (broadcast/Reverb)** e **notificação persistida (database)**,
ambas já com **link de download**.

> Status: **implementado**. Todos os passos abaixo foram aplicados (Export
> `store()`, `GenerateGondolaReportJob`, controller com dispatch, rotas `POST`,
> actions Wayfinder, dropdown com `router.post` + toast, traduções
> `plannerate.reports.*`). Testes: `tests/Feature/Tenant/GondolaReportQueueTest.php`
> (3 passando). Falta apenas validação manual no browser (com Horizon rodando) e a
> limpeza agendada de arquivos antigos (seção 5, opcional).

---

## 1. Contexto — o que já foi feito

Os relatórios foram trazidos do projeto antigo e adaptados ao package
`callcocam/laravel-raptor-plannerate`:

- **Namespaces corrigidos** (`App\…` → `Callcocam\LaravelRaptorPlannerate\…`) em:
  - `src/Exports/GondolaReportExport.php`, `GondolaCompraReportExport.php`,
    `GondolaDimensaoReportExport.php`, `GondolaImageReportExport.php`
  - `src/Http/Controllers/Export/GondolaReportController.php`
  - `src/Services/Reports/GondolaReportService.php`
- **PDF server-side** habilitado com `barryvdh/laravel-dompdf` (`^3.1`); view
  registrada como `plannerate::gondola-report`
  (`->hasViews('plannerate')` no service provider); blade em
  `resources/views/gondola-report.blade.php`.
- **Rotas** em `routes/export.php`, grupo `export/gondola-report`, middleware
  `['auth', 'tenant.client.redirect']`, nomes `export.gondola-report.*`.
- **Dropdown** `resources/js/components/plannerate/DropdownReports.vue` ligado às
  actions Wayfinder (Compra Excel, Reposição Excel, Reposição PDF).
- **Correção do model**: `Product` não tem mais relações `dimensions`/`image` —
  são **colunas diretas** (`width`, `height`, `depth`, `url`). O service foi
  ajustado para usá-las.

### O problema

A geração é **síncrona**: o request HTTP monta o `reportData` (que percorre
seções → prateleiras → segmentos → layers → produtos e ainda busca produtos da
biblioteca) e só então devolve o arquivo. Em gôndolas grandes isso passa de
**6.000 produtos** e leva vários segundos — perto/ além do timeout de request do
PHP-FPM/nginx, e trava a UI enquanto o usuário espera o download.

**Solução:** enfileirar a geração e notificar quando o arquivo estiver pronto,
com link de download na própria notificação.

---

## 2. Infraestrutura existente que vamos reusar

Tudo abaixo **já está pronto** — não precisa criar.

### 2.1 Filas / Horizon
- `config/queue.php`: driver **redis**.
- `config/horizon.php`: supervisors nomeados — `critical`, **`default`** (timeout
  660s, 1-3 workers), `imports-fetch`, `imports-process`, `maintenance`,
  `ai-research`. Para relatórios, a fila **`default`** já serve (timeout 660s é
  folgado). Se o volume crescer, criar um supervisor dedicado `reports`.

### 2.2 Multi-tenancy em jobs (Spatie)
- `config/multitenancy.php`: `queues_are_tenant_aware_by_default => true`.
- Jobs que implementam `Spatie\Multitenancy\Jobs\TenantAware` têm o **tenant
  restaurado automaticamente** antes do `handle()` (padrão usado por
  `ProcessProductImageWithAiJob`, `ImportCategoriesFromSpreadsheetJob`).
- **Sempre** passar `tenantId` também como propriedade do job, para uso em
  `failed()` (onde o tenant pode não estar restaurado).

### 2.3 Notificações (database + broadcast) — `app/Notifications/AppNotification.php`
Classe genérica, já `ShouldQueue`, canais `['database', 'broadcast']`:

```php
new AppNotification(
    title: 'Relatório pronto',
    message: 'Seu relatório de reposição está disponível para download.',
    type: 'success',                 // 'info'|'success'|'warning'|'error'
    actionUrl: null,                 // link "Ver detalhes" (opcional)
    downloadUrl: 'reports/{tenant}/arquivo.xlsx', // caminho no disco `local`
    downloadName: 'relatorio-reposicao.xlsx',     // nome amigável do download
    tenantId: (string) Tenant::current()?->getKey(),
);
```

- **Tela (broadcast):** `NotificationsDropdown.vue` escuta o canal privado
  `App.Models.User.{id}` (Reverb) via `useEchoNotification` e mostra o item em
  tempo real. `NotificationItem.vue` já renderiza o botão **"Baixar arquivo"**
  quando `data.download_url` existe.
- **Persistência (database):** gravada na tabela `notifications` (tenant-scoped;
  coluna `tenant_id` preenchida). Aparece no sino mesmo se o usuário estava
  offline no momento.

### 2.4 Download do arquivo — já roteado
`GET /notifications/{id}/download` → `Tenant\NotificationController::download`
(nome `tenant.notifications.download`). Ele lê `notification.data['download_url']`,
valida por usuário **e** `tenant_id`, e faz `response()->download()` do disco
**`local`** (`storage/app/private/…`). **Nada a fazer aqui** — basta o job gravar
o arquivo em `local` e passar o caminho relativo em `downloadUrl`.

### 2.5 Storage
- Disco **`local`** = `storage/app/private` (privado) — usar para os relatórios.
- Isolamento por tenant é **por convenção de path**: `reports/{tenantId}/{arquivo}`.

### 2.6 Padrão opcional de "status" (polling)
`ProductImageAiOperation` + `GET …/operations/{id}` mostram um padrão de
tabela-de-operação + polling. **Para relatórios não é necessário** — a
notificação broadcast já avisa a conclusão. Adote o polling só se quiser uma
barra de progresso na própria tela.

---

## 3. Arquitetura proposta

```
Dropdown (router.post)
   │
   ▼
GondolaReportController@generate*          (contexto tenant, auth)
   │  dispatch job + back()/202
   ▼
GenerateGondolaReportJob (ShouldQueue, TenantAware)   fila: default
   │  1. GondolaReportService->generateReportData()
   │  2. monta arquivo (Export xlsx | dompdf pdf)
   │  3. Storage::disk('local')->put("reports/{tenant}/{arquivo}")
   │  4. $user->notify(new AppNotification(downloadUrl, downloadName, ...))
   ▼
AppNotification → [database]  grava em notifications (tenant)
              → [broadcast]  Reverb → Echo → NotificationsDropdown (tela)
   │
   ▼
Usuário clica "Baixar arquivo" → GET /notifications/{id}/download
```

---

## 4. Passo a passo de implementação

### Passo 1 — Export classes: persistir em disco (além de streamar)

Hoje cada Export monta um `Spreadsheet` e devolve `StreamedResponse` via
`download()`. Adicione um método que **grava em um caminho absoluto** (o Xlsx
writer só escreve em path de filesystem, não em disco Laravel abstrato):

```php
// src/Exports/GondolaReportExport.php (e nas outras 3 Export classes)
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Grava a planilha em um caminho absoluto no filesystem.
 * Usado pela geração em fila (o writer do PhpSpreadsheet exige path físico).
 */
public function store(string $absolutePath): void
{
    $writer = new Xlsx($this->createSpreadsheet());
    $writer->save($absolutePath);
}
```

> `createSpreadsheet()` já existe e é privado; o novo método fica na mesma classe,
> então tem acesso. Mantenha `download()` como está (ainda útil para preview
> síncrono/admin, se quiser).

Para o **PDF** não precisa mudar nada nas Export — o dompdf gera bytes
diretamente (`Pdf::loadView(...)->output()`), como validado.

### Passo 2 — Criar o Job

`packages/callcocam/laravel-raptor-plannerate/src/Jobs/GenerateGondolaReportJob.php`:

```php
<?php

namespace Callcocam\LaravelRaptorPlannerate\Jobs;

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
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Jobs\TenantAware;
use Spatie\Multitenancy\Models\Tenant;

/**
 * Gera um relatório de gôndola em fila e notifica o usuário ao concluir.
 * TenantAware: o Spatie restaura o tenant antes do handle().
 */
class GenerateGondolaReportJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Geração pesada: sem retry automático para não duplicar arquivo. */
    public int $tries = 1;

    /** Folga para gôndolas grandes (a fila `default` tem timeout 660s). */
    public int $timeout = 600;

    /**
     * @param  string  $format  excel|pdf|compra|dimensao|image
     */
    public function __construct(
        public string $gondolaId,
        public string $format,
        public string $userId,
        public string $tenantId,
    ) {
        $this->onQueue('default'); // trocar por 'reports' se criar supervisor dedicado
    }

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

    /** Grava um Export xlsx no disco `local` e devolve [pathRelativo, nome]. */
    private function storeExcel(object $export, string $dir, string $name): array
    {
        $relative = "{$dir}/{$name}";
        Storage::disk('local')->makeDirectory($dir);
        $export->store(Storage::disk('local')->path($relative));

        return [$relative, $name];
    }

    /** Gera o PDF (dompdf) e grava no disco `local`. */
    private function storePdf(array $data, string $dir, string $slug): array
    {
        $name = "relatorio-reposicao-{$slug}.pdf";
        $relative = "{$dir}/{$name}";
        $pdf = Pdf::loadView('plannerate::gondola-report', $data)->setPaper('a4', 'landscape');
        Storage::disk('local')->put($relative, $pdf->output());

        return [$relative, $name];
    }

    /** Falha → notifica o usuário (tenant pode não estar restaurado aqui). */
    public function failed(\Throwable $e): void
    {
        Tenant::find($this->tenantId)?->makeCurrent();
        $this->notify(
            title: 'Falha ao gerar relatório',
            message: 'Não foi possível gerar o relatório. Tente novamente.',
            type: 'error',
        );
    }

    /** Dispara AppNotification para o usuário solicitante. */
    private function notify(
        string $title,
        string $message,
        string $type,
        ?string $downloadUrl = null,
        ?string $downloadName = null,
    ): void {
        User::find($this->userId)?->notify(new AppNotification(
            title: $title,
            message: $message,
            type: $type,
            downloadUrl: $downloadUrl,
            downloadName: $downloadName,
            tenantId: $this->tenantId,
        ));
    }
}
```

**Notas importantes**
- `AppNotification` é `NotTenantAware`, mas o **model `User`** resolve a conexão
  (tenant/landlord) dinamicamente — a notificação database cai na tabela
  `notifications` do tenant correto porque o tenant está corrente no `handle()`.
- Passar `tenantId` no construtor da notificação garante o preenchimento da
  coluna `tenant_id` (via listener `UpdateNotificationTenantId`), essencial para o
  filtro de download.
- `tries = 1`: geração pesada e não idempotente (nome com timestamp). Se quiser
  retry, torne o nome do arquivo determinístico por (gondola, formato, dia).

### Passo 3 — Controller: despachar em vez de streamar

Em `GondolaReportController`, troque cada método por um dispatch. Sugestão:
consolidar num único método parametrizado, ou manter os 6 delegando ao job:

```php
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateGondolaReportJob;
use Spatie\Multitenancy\Models\Tenant;

private function queueReport(string $gondolaId, string $format)
{
    GenerateGondolaReportJob::dispatch(
        $gondolaId,
        $format,
        (string) auth()->id(),
        (string) Tenant::current()?->getKey(),
    );

    // Rota consumida via router.post do Inertia → back() com flash.
    return back()->with('success', __('plannerate.reports.queued'));
}

public function generateExcelReport(string $gondolaId)   { return $this->queueReport($gondolaId, 'excel'); }
public function generatePdfReport(string $gondolaId)      { return $this->queueReport($gondolaId, 'pdf'); }
public function generateCompraReport(string $gondolaId)   { return $this->queueReport($gondolaId, 'compra'); }
public function generateDimensaoReport(string $gondolaId) { return $this->queueReport($gondolaId, 'dimensao'); }
public function generateImageReport(string $gondolaId)    { return $this->queueReport($gondolaId, 'image'); }
```

`getReportData()` (JSON) pode continuar síncrono — é usado para preview, não gera
arquivo.

### Passo 4 — Rotas: `GET` → `POST`

Geração agora é **mutação** (dispara job). Em `routes/export.php`, troque os
verbos dos 5 relatórios para `POST` (mantenha `data` como `GET`):

```php
Route::controller(GondolaReportController::class)
    ->prefix('export/gondola-report')
    ->name('export.gondola-report.')
    ->middleware(['auth', 'tenant.client.redirect'])
    ->group(function () {
        Route::post('{gondola}/excel', 'generateExcelReport')->name('excel');
        Route::post('{gondola}/pdf', 'generatePdfReport')->name('pdf');
        Route::post('{gondola}/compra', 'generateCompraReport')->name('compra');
        Route::post('{gondola}/dimensao', 'generateDimensaoReport')->name('dimensao');
        Route::post('{gondola}/image', 'generateImageReport')->name('image');
        Route::get('{gondola}/data', 'getReportData')->name('data');
    });
```

Regenerar/ajustar as actions Wayfinder
(`resources/js/actions/.../Export/GondolaReportController.ts`) para refletir
`POST`. **Não rodar `wayfinder:generate`** (regra do projeto) — se necessário,
ajustar o arquivo à mão mantendo o formato gerado.

### Passo 5 — Frontend: `router.post` + toast

Em `DropdownReports.vue`, trocar `window.open` por `router.post` (regra do
projeto: usar o `router` do Inertia para mutações):

```ts
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner'; // ver componente de toast já usado no projeto
import { useT } from '@/composables/useT';

const { t } = useT();

type ReportAction = { url: (g: string) => string };

function queueReport(action: ReportAction): void {
    const id = currentGondola.value?.id;
    if (!id) return;

    router.post(action.url(id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success(t('plannerate.reports.queued')),
        onError: () => toast.error(t('plannerate.reports.queue_failed')),
    });
}

function handlePurchaseExcel() { queueReport(generateCompraReport); }
function handleRestockExcel()  { queueReport(generateExcelReport); }
function handleRestockPdf()    { queueReport(generatePdfReport); }
```

Ao concluir, a notificação broadcast aparece no sino com o botão **"Baixar
arquivo"** — **sem nenhuma tela nova a construir**.

### Passo 6 — Traduções

Adicionar em `lang/pt_BR/plannerate/` (namespace de relatórios):

```php
'reports' => [
    'queued' => 'Relatório sendo gerado. Você será avisado quando estiver pronto.',
    'queue_failed' => 'Não foi possível iniciar a geração do relatório.',
],
```

---

## 5. Limpeza de arquivos antigos

Os PDFs/planilhas ficam em `storage/app/private/reports/{tenant}/`. Adicionar um
comando agendado (fila `maintenance`) para expurgar arquivos com mais de N dias:

```php
// Console\Kernel schedule
$schedule->call(function () {
    $cutoff = now()->subDays(7);
    foreach (Storage::disk('local')->allFiles('reports') as $file) {
        if (Storage::disk('local')->lastModified($file) < $cutoff->timestamp) {
            Storage::disk('local')->delete($file);
        }
    }
})->daily();
```

> Opcional: também remover as notificações database correspondentes já lidas e
> vencidas, para não acumular links quebrados no sino.

---

## 6. Multi-tenancy — checklist de corretude

- [ ] Job **implementa `TenantAware`** (tenant restaurado no `handle()`).
- [ ] Job dispara **de dentro de contexto tenant** (rotas tenant, auth) — o Spatie
      tagueia o tenant no dispatch (`queues_are_tenant_aware_by_default = true`).
- [ ] `tenantId` passado ao construtor do job **e** ao `AppNotification`.
- [ ] Arquivos gravados em `reports/{tenantId}/…` (isolamento por path).
- [ ] `failed()` re-seleciona o tenant (`Tenant::find(...)->makeCurrent()`) antes
      de notificar.
- [ ] Download já é filtrado por `user` + `tenant_id` no `NotificationController`.

---

## 7. Testes (Pest)

```php
// Feature: dispatch
it('enfileira o relatório em vez de gerar sincronamente', function () {
    Queue::fake();
    // autenticar usuário no tenant `alberti`, ter uma gôndola
    $this->post(route('tenant.export.gondola-report.excel', $gondola))
        ->assertRedirect();
    Queue::assertPushed(GenerateGondolaReportJob::class);
});

// Feature/Unit: job gera arquivo + notifica
it('gera o arquivo no disco local e notifica o usuário', function () {
    Storage::fake('local');
    Notification::fake();
    (new GenerateGondolaReportJob($gondola->id, 'excel', $user->id, $tenantId))
        ->handle(app(GondolaReportService::class));
    Storage::disk('local')->assertExists("reports/{$tenantId}/…");
    Notification::assertSentTo($user, AppNotification::class);
});
```

Rodar isolado (a suíte tenant completa é flaky):
`docker compose exec php php artisan test --compact --filter=GondolaReport`.

---

## 8. Resumo do esforço

| Item | Arquivo | Ação |
|---|---|---|
| Persistir Excel | 4 × `src/Exports/*Export.php` | + método `store()` |
| Job | `src/Jobs/GenerateGondolaReportJob.php` | **novo** |
| Controller | `src/Http/Controllers/Export/GondolaReportController.php` | dispatch + `back()` |
| Rotas | `routes/export.php` | `GET` → `POST` (5 rotas) |
| Actions | `resources/js/actions/.../Export/GondolaReportController.ts` | ajustar p/ `POST` |
| Dropdown | `resources/js/components/plannerate/DropdownReports.vue` | `router.post` + toast |
| Traduções | `lang/pt_BR/plannerate/…` | chaves `reports.queued/queue_failed` |
| Limpeza | `Console\Kernel` | expurgo agendado |

**Reuso zero-esforço:** notificação em tela + database, link/botão de download,
rota de download, isolamento por tenant, filas/Horizon. Tudo já existe.
