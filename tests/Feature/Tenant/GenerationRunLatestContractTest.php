<?php

use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato do endpoint `.../generation-runs/latest` que alimenta o overlay de
 * "geração em andamento" do editor (useGenerationRun.ts / GenerationOverlay.vue).
 * O frontend deriva isGenerating/hasFailed/isStuck e o "Tentar novamente" destes
 * mesmos campos — sem eles chegando certos, o overlay não tem como decidir nada.
 */

/** Coloca um tenant "corrente", reaproveitando o database da conexão de teste. */
function fakeOverlayTenant(string $tenantId): Tenant
{
    $defaultConnection = (string) config('database.default');

    $tenant = new Tenant;
    $tenant->id = $tenantId;
    $tenant->database = (string) config("database.connections.{$defaultConnection}.database", 'testing');

    Tenant::forgetCurrent();
    $tenant->makeCurrent();

    return $tenant;
}

/** Schema mínimo do tenant para servir o endpoint. */
function buildOverlaySchema(): void
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
function makeOverlayGondola(string $planogramId, string $gondolaId): Gondola
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

beforeEach(function (): void {
    fakeOverlayTenant('01jtenantoverlay0000000000');
    test()->actingAs(tap(new User, fn (User $u) => $u->id = '01juseroverlay00000000000'));
    buildOverlaySchema();
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('run em queued há minutos chega com is_pending e created_at — é o que o front usa pra decidir o estado "travada"', function (): void {
    $gondola = makeOverlayGondola('01jplanogramoverlayq000000', '01jgondolaoverlayq00000000');

    (new PlanogramGenerationRun)->forceFill([
        'id' => '01jrunoverlayqueued0000000',
        'planogram_id' => $gondola->planogram_id,
        'gondola_id' => $gondola->id,
        'status' => 'queued',
        'mode' => 'automatic',
        'config_snapshot' => ['strategy' => 'abc'],
        // Simula um run parado há 5min — o front (isStuck) calcula isso a partir
        // de created_at; se o endpoint não devolvê-lo, o overlay nunca detecta.
        'created_at' => now()->subMinutes(5),
    ])->save();

    $response = app(PlanogramGenerationRunController::class)->latest($gondola->id);
    $data = $response->getData(true)['data'];

    expect($data['status'])->toBe('queued')
        ->and($data['is_pending'])->toBeTrue()
        ->and($data['created_at'])->not->toBeNull();
});

test('run em running não é mais pendente-travável e some da contagem de tempo parado', function (): void {
    $gondola = makeOverlayGondola('01jplanogramoverlayr000000', '01jgondolaoverlayr00000000');

    (new PlanogramGenerationRun)->forceFill([
        'id' => '01jrunoverlayrunning00000',
        'planogram_id' => $gondola->planogram_id,
        'gondola_id' => $gondola->id,
        'status' => 'running',
        'mode' => 'automatic',
        'config_snapshot' => ['strategy' => 'abc'],
        'started_at' => now(),
    ])->save();

    $response = app(PlanogramGenerationRunController::class)->latest($gondola->id);
    $data = $response->getData(true)['data'];

    expect($data['status'])->toBe('running')
        ->and($data['is_pending'])->toBeTrue();
});

test('run falho chega com error_message e o snapshot que o "Tentar novamente" precisa pra reproduzir a mesma configuração', function (): void {
    $gondola = makeOverlayGondola('01jplanogramoverlayf000000', '01jgondolaoverlayf00000000');

    (new PlanogramGenerationRun)->forceFill([
        'id' => '01jrunoverlayfailed000000',
        'planogram_id' => $gondola->planogram_id,
        'gondola_id' => $gondola->id,
        'status' => 'failed',
        'mode' => 'template',
        'template_id' => '01jtemplateoverlay0000000',
        'config_snapshot' => ['strategy' => 'abc', 'template_id' => '01jtemplateoverlay0000000'],
        'error_message' => 'Nenhum produto elegível para os slots do template.',
        'finished_at' => now(),
    ])->save();

    $response = app(PlanogramGenerationRunController::class)->latest($gondola->id);
    $data = $response->getData(true)['data'];

    expect($data['status'])->toBe('failed')
        ->and($data['is_pending'])->toBeFalse()
        ->and($data['error_message'])->toBe('Nenhum produto elegível para os slots do template.')
        ->and($data['config_snapshot']['strategy'])->toBe('abc')
        ->and($data['template_id'])->toBe('01jtemplateoverlay0000000');
});

test('gôndola sem execução devolve data null — o overlay não deve aparecer', function (): void {
    $gondola = makeOverlayGondola('01jplanogramoverlaynone000', '01jgondolaoverlaynone00000');

    $response = app(PlanogramGenerationRunController::class)->latest($gondola->id);

    expect($response->getData(true)['data'])->toBeNull();
});
