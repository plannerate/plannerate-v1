<?php

use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoGenerationResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramOutput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\ValidationReport;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Exceptions\GenerationCancelledException;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\AutoPlanogramController;
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateAutoPlanogramJob;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationQueueDispatcher;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationReportBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Fase 0 do plano de precisão da gôndola (docs/gondola-precisao-automatica/):
 * a geração deixa de ser síncrona e passa a rodar em fila, persistindo cada
 * execução em planogram_generation_runs para consulta posterior.
 */

/** Coloca um tenant "corrente", reaproveitando o database da conexão de teste. */
function fakeGenerationTenant(string $tenantId): Tenant
{
    $defaultConnection = (string) config('database.default');

    $tenant = new Tenant;
    $tenant->id = $tenantId;
    $tenant->database = (string) config("database.connections.{$defaultConnection}.database", 'testing');

    Tenant::forgetCurrent();
    $tenant->makeCurrent();

    return $tenant;
}

/** Autentica um usuário em memória — suficiente para auth()->id(). */
function actingAsGenerationUser(string $userId): User
{
    $user = new User;
    $user->id = $userId;
    test()->actingAs($user);

    return $user;
}

/** Cria planograma + gôndola no tenant. forceFill: `id` não é fillable nesses models. */
function makeGenerationGondola(string $planogramId, string $gondolaId): Gondola
{
    (new Planogram)->forceFill([
        'id' => $planogramId,
        'name' => 'Planograma de teste',
    ])->save();

    $gondola = new Gondola;
    $gondola->forceFill([
        'id' => $gondolaId,
        'planogram_id' => $planogramId,
        'name' => 'Gôndola de teste',
    ])->save();

    return $gondola;
}

/** Schema mínimo do tenant para o caminho de dispatch do controller. */
function buildGenerationSchema(): void
{
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('planograms', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->string('name')->nullable();
        $table->string('template_id')->nullable();
        $table->string('generation_mode')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_generation_runs', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26);
        $table->char('gondola_id', 26);
        $table->char('user_id', 26)->nullable();
        $table->string('status')->default('queued');
        $table->string('mode');
        $table->json('config_snapshot');
        $table->char('template_id', 26)->nullable();
        $table->char('synth_template_id', 26)->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->unsignedInteger('duration_ms')->nullable();
        $table->decimal('occupancy_avg', 5, 4)->nullable();
        $table->decimal('occupancy_min', 5, 4)->nullable();
        $table->decimal('occupancy_max', 5, 4)->nullable();
        $table->unsignedSmallInteger('iterations_run')->nullable();
        $table->boolean('converged')->nullable();
        $table->json('capacity_report')->nullable();
        $table->json('validation_report')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('regenerateAuto enfileira o job em vez de gerar sincronamente', function (): void {
    Queue::fake();

    // Ordem importa: makeCurrent() reconecta a conexão `tenant` (:memory:), o que
    // descartaria um schema criado antes dele.
    $tenant = fakeGenerationTenant('01jtenantautogen0000000000');
    $user = actingAsGenerationUser('01juserautogen000000000000');
    buildGenerationSchema();

    $gondola = makeGenerationGondola('01jplanogramautogen0000000', '01jgondolaautogen000000000');

    $response = app(AutoPlanogramController::class)->regenerateAuto($gondola->id);

    // Mutação consumida via router.post → redirect (back), imediato.
    expect($response)->toBeInstanceOf(RedirectResponse::class);

    Queue::assertPushed(GenerateAutoPlanogramJob::class, fn (GenerateAutoPlanogramJob $job): bool => $job->gondolaId === $gondola->id
        && $job->planogramId === '01jplanogramautogen0000000'
        && $job->templateId === null
        && $job->userId === (string) $user->id
        && $job->tenantId === (string) $tenant->id);
});

test('a execução é persistida como queued, com o snapshot da configuração', function (): void {
    Queue::fake();

    fakeGenerationTenant('01jtenantautogenrun0000000');
    actingAsGenerationUser('01juserautogenrun000000000');
    buildGenerationSchema();

    $gondola = makeGenerationGondola('01jplanogramautogenrun0000', '01jgondolaautogenrun000000');

    app(AutoPlanogramController::class)->regenerateAuto($gondola->id);

    $run = PlanogramGenerationRun::query()->where('gondola_id', $gondola->id)->firstOrFail();

    expect($run->status)->toBe(GenerationRunStatus::Queued)
        ->and($run->mode)->toBe('automatic')
        // O snapshot permite auditar depois com que parâmetros a gôndola foi gerada.
        ->and($run->config_snapshot['strategy'])->toBe('abc')
        ->and($run->occupancy_avg)->toBeNull()
        ->and($run->finished_at)->toBeNull();

    // O job despachado aponta para o run criado.
    Queue::assertPushed(GenerateAutoPlanogramJob::class, fn (GenerateAutoPlanogramJob $job): bool => $job->runId === $run->id);
});

test('o dispatcher é a fonte única dos dois caminhos de geração', function (): void {
    // Regressão: a criação de gôndola em modo automático (GondolaController::store)
    // gerava SINCRONAMENTE, escapando da fila. Ambos os caminhos passam pelo mesmo
    // GenerationQueueDispatcher — este teste cobre o caminho da criação.
    Queue::fake();

    $tenant = fakeGenerationTenant('01jtenantdispatcher0000000');
    $user = actingAsGenerationUser('01juserdispatcher000000000');
    buildGenerationSchema();

    $gondola = makeGenerationGondola('01jplanogramdispatcher0000', '01jgondoladispatcher000000');
    $planogram = Planogram::query()->findOrFail('01jplanogramdispatcher0000');

    $config = new AutoGenerateConfigDTO(
        strategy: 'abc',
        useExistingAnalysis: true,
        startDate: null,
        endDate: null,
    );

    $run = app(GenerationQueueDispatcher::class)->dispatch($gondola, $planogram, $config, templateId: null);

    expect($run->status)->toBe(GenerationRunStatus::Queued)
        ->and($run->mode)->toBe('automatic');

    Queue::assertPushed(GenerateAutoPlanogramJob::class, fn (GenerateAutoPlanogramJob $job): bool => $job->runId === $run->id
        && $job->gondolaId === $gondola->id
        && $job->userId === (string) $user->id
        && $job->tenantId === (string) $tenant->id);
});

test('o job de geração roda na fila default, sem retry e é TenantAware', function (): void {
    $job = new GenerateAutoPlanogramJob(
        gondolaId: '01jgondola',
        planogramId: '01jplanogram',
        config: (new AutoGenerateConfigDTO(
            strategy: 'abc',
            useExistingAnalysis: true,
            startDate: null,
            endDate: null,
        ))->toArray(),
        templateId: null,
        userId: '01juser',
        tenantId: '01jtenant',
        runId: '01jrun',
    );

    expect($job->queue)->toBe('default')
        ->and($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(600)
        ->and($job)->toBeInstanceOf(TenantAware::class);
});

test('as métricas de ocupação são derivadas do slot_analysis da execução', function (): void {
    // percentual_uso vem em 0-100 do placement engine; a coluna guarda 0-1.
    $output = new PlanogramOutput(
        gondolaId: '01jgondola',
        placedSegments: collect(),
        rejectedProducts: collect(),
        validationReport: new ValidationReport(true),
        slotAnalysis: [
            ['percentual_uso' => 70, 'largura_livre' => 30.0],
            ['percentual_uso' => 90, 'largura_livre' => 10.0],
            ['percentual_uso' => 80, 'largura_livre' => 20.0],
        ],
    );

    $metrics = app(GenerationReportBuilder::class)->buildOccupancyMetrics(
        new AutoGenerationResult($output, synthTemplateId: null, totalInputProducts: 0),
    );

    expect($metrics['occupancy_avg'])->toBe(0.8)
        ->and($metrics['occupancy_min'])->toBe(0.7)
        ->and($metrics['occupancy_max'])->toBe(0.9);
});

test('sem slot_analysis as métricas de ocupação ficam nulas em vez de zero', function (): void {
    // Zero significaria "prateleiras vazias"; null significa "não medido" — a
    // distinção importa para comparar execuções ao longo do tempo.
    $output = new PlanogramOutput(
        gondolaId: '01jgondola',
        placedSegments: collect(),
        rejectedProducts: collect(),
        validationReport: new ValidationReport(true),
        slotAnalysis: [],
    );

    $metrics = app(GenerationReportBuilder::class)->buildOccupancyMetrics(
        new AutoGenerationResult($output, synthTemplateId: null, totalInputProducts: 0),
    );

    expect($metrics['occupancy_avg'])->toBeNull()
        ->and($metrics['occupancy_min'])->toBeNull()
        ->and($metrics['occupancy_max'])->toBeNull();
});

/*
 * Cancelamento de negócio × falha técnica.
 *
 * O job capturava `\RuntimeException` para tratar "nenhum produto elegível". Só que
 * QueryException TAMBÉM é uma RuntimeException (via PDOException): uma falha real de banco
 * (a tabela ausente `product_analyses`) era engolida e mostrada ao usuário como "geração
 * cancelada", sem nunca ser tratada como o defeito que era.
 */

test('cancelamento de negócio é um tipo próprio, não uma RuntimeException qualquer', function (): void {
    $cancelamento = new GenerationCancelledException('nenhum produto');

    expect($cancelamento)->toBeInstanceOf(RuntimeException::class);

    // A recíproca NÃO vale: erro de banco é RuntimeException, mas não é cancelamento.
    $erroDeBanco = new QueryException('tenant', 'select 1', [], new Exception('relation does not exist'));

    expect($erroDeBanco)->toBeInstanceOf(RuntimeException::class)
        ->and($erroDeBanco)->not->toBeInstanceOf(GenerationCancelledException::class);
});

test('o job só trata como cancelamento a exceção de negócio — erro técnico estoura', function (): void {
    $reflection = new ReflectionMethod(GenerateAutoPlanogramJob::class, 'handle');
    $corpo = file_get_contents($reflection->getFileName());

    // A guarda que impede a regressão: capturar RuntimeException aqui voltaria a engolir
    // QueryException e a esconder falha de infraestrutura atrás de um aviso amigável.
    expect($corpo)->toContain('catch (GenerationCancelledException')
        ->and($corpo)->not->toContain('catch (\RuntimeException');
});

/*
 * A ocupação relatada tem que bater com a gôndola que o usuário vê.
 *
 * A métrica era derivada do slot_analysis, que é montado ANTES do overflow pass — então tudo
 * que o overflow coloca ficava de fora da conta. Numa gôndola real ela subiu de 83,3% para
 * 87,0% e o relatório ficou cravado em 76,8% nas duas: o usuário via a gôndola mudar e o
 * número não mexer, e concluiu (com razão) que "não mudou nada".
 */

test('a ocupação vem das prateleiras físicas, não do slot_analysis anterior ao overflow', function (): void {
    $output = new PlanogramOutput(
        gondolaId: '01jgondola',
        placedSegments: collect(),
        rejectedProducts: collect(),
        validationReport: new ValidationReport(true),
        // O slot_analysis está DESATUALIZADO (é anterior ao overflow) e diz 50%…
        slotAnalysis: [
            ['percentual_uso' => 50, 'largura_livre' => 50.0],
        ],
        // …mas as prateleiras físicas, no fim de tudo, estão em 90%.
        shelfAnalysis: [
            ['shelf_id' => 's1', 'section_id' => 'sec1', 'largura_total' => 100.0, 'largura_usada' => 100.0, 'largura_livre' => 0.0, 'percentual_uso' => 100, 'segmentos' => 5],
            ['shelf_id' => 's2', 'section_id' => 'sec1', 'largura_total' => 100.0, 'largura_usada' => 80.0, 'largura_livre' => 20.0, 'percentual_uso' => 80, 'segmentos' => 3],
        ],
    );

    $metrics = app(GenerationReportBuilder::class)->buildOccupancyMetrics(
        new AutoGenerationResult($output, synthTemplateId: null, totalInputProducts: 0),
    );

    expect($metrics['occupancy_avg'])->toBe(0.9)
        ->and($metrics['occupancy_min'])->toBe(0.8)
        ->and($metrics['occupancy_max'])->toBe(1.0);
});

test('prateleira vazia entra na média com 0% — ela é o defeito, não pode ser omitida', function (): void {
    $output = new PlanogramOutput(
        gondolaId: '01jgondola',
        placedSegments: collect(),
        rejectedProducts: collect(),
        validationReport: new ValidationReport(true),
        shelfAnalysis: [
            ['shelf_id' => 's1', 'section_id' => 'sec1', 'largura_total' => 100.0, 'largura_usada' => 100.0, 'largura_livre' => 0.0, 'percentual_uso' => 100, 'segmentos' => 4],
            ['shelf_id' => 's2', 'section_id' => 'sec1', 'largura_total' => 100.0, 'largura_usada' => 0.0, 'largura_livre' => 100.0, 'percentual_uso' => 0, 'segmentos' => 0],
        ],
    );

    $metrics = app(GenerationReportBuilder::class)->buildOccupancyMetrics(
        new AutoGenerationResult($output, synthTemplateId: null, totalInputProducts: 0),
    );

    // Metade da gôndola está zerada: a média TEM que denunciar isso (50%), não anunciar 100%.
    expect($metrics['occupancy_avg'])->toBe(0.5);
});
