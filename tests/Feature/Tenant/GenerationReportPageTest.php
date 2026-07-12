<?php

use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\GenerationReportPageController;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Página do relatório da geração: o editor deixou de despejar os relatórios embaixo
 * do canvas e passou a linkar para esta página, que serve a última execução por
 * padrão — ou a pedida via `?run=`.
 */

/** Coloca um tenant "corrente", reaproveitando o database da conexão de teste. */
function fakeReportTenant(string $tenantId): Tenant
{
    $defaultConnection = (string) config('database.default');

    $tenant = new Tenant;
    $tenant->id = $tenantId;
    $tenant->database = (string) config("database.connections.{$defaultConnection}.database", 'testing');

    Tenant::forgetCurrent();
    $tenant->makeCurrent();

    return $tenant;
}

/** Schema mínimo do tenant para servir a página. */
function buildReportSchema(): void
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

/** Gôndola de teste com planograma pai. forceFill: `id` não é fillable nesses models. */
function makeReportGondola(string $planogramId, string $gondolaId): Gondola
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
        'generation_mode' => 'automatic',
    ])->save();

    return $gondola;
}

/** Execução concluída, com o relatório de capacidade que a página exibe. */
function makeReportRun(string $runId, string $planogramId, string $gondolaId, int $placed): PlanogramGenerationRun
{
    $run = new PlanogramGenerationRun;

    $run->forceFill([
        'id' => $runId,
        'planogram_id' => $planogramId,
        'gondola_id' => $gondolaId,
        'status' => 'completed',
        'mode' => 'automatic',
        'config_snapshot' => ['strategy' => 'abc'],
        'occupancy_avg' => 0.85,
        'finished_at' => now(),
        'capacity_report' => [
            'total_produtos' => 10,
            'posicionados' => $placed,
            'rejeitados_espaco' => 10 - $placed,
            'mix_excede_gondola' => true,
        ],
        'validation_report' => ['error_count' => 0, 'warning_count' => 1, 'info_count' => 0, 'results' => []],
    ])->save();

    return $run;
}

/** Página renderizada pelo controller, já como array (props do Inertia). */
function renderReportPage(string $gondolaId, array $query = []): array
{
    $request = Request::create(
        "/editor/gondolas/{$gondolaId}/generation-report",
        'GET',
        $query,
        server: ['HTTP_X_INERTIA' => 'true'],
    );

    $response = app(GenerationReportPageController::class)->show($request, $gondolaId);

    return $response->toResponse($request)->getData(true);
}

beforeEach(function (): void {
    // A página autoriza com a MESMA policy do editor (GondolaPolicy::view), que
    // depende de RBAC/módulos no landlord. Aqui o foco é o contrato da página, então
    // o Gate é dublado — a policy em si é coberta pelos testes do editor.
    Gate::shouldReceive('authorize')->andReturnTrue();

    fakeReportTenant('01jtenantreport0000000000');
    test()->actingAs(tap(new User, fn (User $u) => $u->id = '01juserreport00000000000'));
    buildReportSchema();
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('a página serve a última execução da gôndola', function (): void {
    $gondola = makeReportGondola('01jplanogramreport0000000', '01jgondolareport000000000');

    // Ids monotônicos, como ULIDs reais: é o que desempata execuções do mesmo segundo.
    makeReportRun('01jrunreporta000000000000', $gondola->planogram_id, $gondola->id, placed: 4);
    // A mais recente (criada depois) é a que a página deve exibir por padrão.
    $latest = makeReportRun('01jrunreportb000000000000', $gondola->planogram_id, $gondola->id, placed: 8);

    $page = renderReportPage($gondola->id);

    expect($page['component'])->toBe('tenant/editor/GenerationReport')
        ->and($page['props']['run']['id'])->toBe($latest->id)
        ->and($page['props']['run']['capacity_report']['posicionados'])->toBe(8)
        ->and($page['props']['run']['validation_report']['warning_count'])->toBe(1)
        // O histórico alimenta o seletor de execuções da página.
        ->and($page['props']['runs'])->toHaveCount(2)
        ->and($page['props']['gondola']['id'])->toBe($gondola->id)
        ->and($page['props']['editorUrl'])->toContain($gondola->id);
});

test('`?run=` seleciona uma execução anterior', function (): void {
    $gondola = makeReportGondola('01jplanogramreportsel0000', '01jgondolareportsel000000');

    $old = makeReportRun('01jrunreportsela00000000', $gondola->planogram_id, $gondola->id, placed: 3);
    makeReportRun('01jrunreportselb00000000', $gondola->planogram_id, $gondola->id, placed: 9);

    $page = renderReportPage($gondola->id, ['run' => $old->id]);

    expect($page['props']['run']['id'])->toBe($old->id)
        ->and($page['props']['run']['capacity_report']['posicionados'])->toBe(3);
});

test('gôndola nunca gerada devolve a página sem execução, em vez de erro', function (): void {
    $gondola = makeReportGondola('01jplanogramreportnone000', '01jgondolareportnone00000');

    $page = renderReportPage($gondola->id);

    expect($page['component'])->toBe('tenant/editor/GenerationReport')
        ->and($page['props']['run'])->toBeNull()
        ->and($page['props']['runs'])->toBeEmpty();
});
