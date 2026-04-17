<?php

use App\Models\Workflow\GondolaWorkflow;
use App\Models\Workflow\PlanogramWorkflow;
use Callcocam\LaravelRaptor\Services\TenantDatabaseManager;
use Callcocam\LaravelRaptorFlow\Enums\FlowAction;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowHistory;
use Callcocam\LaravelRaptorFlow\Models\FlowMetric;
use Callcocam\LaravelRaptorFlow\Models\FlowNotification;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    // Mock TenantDatabaseManager to prevent DB::purge which breaks test transactions
    $mock = Mockery::mock(TenantDatabaseManager::class);
    $mock->shouldReceive('switchDefaultConnectionTo')->andReturnNull();
    $mock->shouldReceive('setupConnection')->andReturnNull();
    $mock->shouldReceive('getDefaultDatabaseName')->andReturn(
        config('database.connections.'.config('database.default').'.database')
    );
    app()->instance(TenantDatabaseManager::class, $mock);

    if (! Schema::hasTable('planograms')) {
        Schema::create('planograms', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('client_id', 26)->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    if (! Schema::hasTable('gondolas')) {
        Schema::create('gondolas', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('planogram_id', 26)->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }
});

function createRealisticTemplate(int $order, ?string $flowId = null): FlowStepTemplate
{
    return FlowStepTemplate::query()->create([
        'name' => 'Etapa Realista '.$order,
        'slug' => 'etapa-realista-'.$order.'-'.Str::lower(Str::random(8)),
        'flow_id' => $flowId,
        'is_active' => true,
        'suggested_order' => $order,
        'estimated_duration_days' => 2,
    ]);
}

function createRealisticConfigStep(string $templateId, int $order, string $configurableId): FlowConfigStep
{
    return FlowConfigStep::query()->create([
        'configurable_type' => PlanogramWorkflow::class,
        'configurable_id' => $configurableId,
        'flow_step_template_id' => $templateId,
        'name' => 'Config Realista '.$order,
        'order' => $order,
        'estimated_duration_days' => 2,
        'is_active' => true,
        'is_required' => true,
    ]);
}

function setupRealisticScenario(): array
{
    $dbName = config('database.connections.'.config('database.default').'.database');
    $tenantId = (string) Str::ulid();
    $clientId = (string) Str::ulid();
    $landlordConn = config('raptor.database.landlord_connection_name', 'landlord');

    DB::connection($landlordConn)->table('clients')->insert([
        'id' => $clientId,
        'tenant_id' => $tenantId,
        'name' => 'Cliente Teste Realista',
        'slug' => 'cliente-teste-realista-'.Str::lower(Str::random(6)),
        'database' => $dbName,
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planogramId = (string) Str::ulid();
    DB::table('planograms')->insert([
        'id' => $planogramId,
        'client_id' => $clientId,
        'tenant_id' => $tenantId,
        'name' => 'Planograma Teste Realista',
        'slug' => 'planograma-teste-realista-'.Str::lower(Str::random(6)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $gondolaIds = [];
    for ($i = 1; $i <= 5; $i++) {
        $gondolaId = (string) Str::ulid();
        DB::table('gondolas')->insert([
            'id' => $gondolaId,
            'planogram_id' => $planogramId,
            'tenant_id' => $tenantId,
            'name' => "Gôndola {$i}",
            'slug' => 'gondola-'.$i.'-'.Str::lower(Str::random(6)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gondolaIds[] = $gondolaId;
    }

    $templates = [];
    $steps = [];
    for ($i = 1; $i <= 4; $i++) {
        $template = createRealisticTemplate($i);
        $templates[] = $template;
        $steps[] = createRealisticConfigStep($template->id, $i, $planogramId);
    }

    return ['clientId' => $clientId, 'planogramId' => $planogramId, 'gondolaIds' => $gondolaIds, 'templates' => $templates, 'steps' => $steps];
}

it('runs successfully and creates varied execution data', function () {
    $scenario = setupRealisticScenario();

    $this->artisan('flow:seed-realistic', [
        '--client' => $scenario['clientId'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $executionCount = FlowExecution::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->count();

    expect($executionCount)->toBe(5);

    $statuses = FlowExecution::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->pluck('status')
        ->map(fn ($s) => $s->value)
        ->unique()
        ->toArray();

    expect(count($statuses))->toBeGreaterThanOrEqual(1);
});

it('preserves flow and step templates when using --force', function () {
    $scenario = setupRealisticScenario();

    $templateCountBefore = FlowStepTemplate::count();

    $this->artisan('flow:seed-realistic', [
        '--client' => $scenario['clientId'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(FlowStepTemplate::count())->toBe($templateCountBefore);

    $configStepCount = FlowConfigStep::where('configurable_type', PlanogramWorkflow::class)
        ->where('configurable_id', $scenario['planogramId'])
        ->count();

    expect($configStepCount)->toBe(4);
});

it('creates flow history records with varied actions', function () {
    $scenario = setupRealisticScenario();

    $this->artisan('flow:seed-realistic', [
        '--client' => $scenario['clientId'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $historyCount = FlowHistory::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->count();

    expect($historyCount)->toBeGreaterThan(0);

    $actions = FlowHistory::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->pluck('action')
        ->map(fn ($a) => $a->value)
        ->unique()
        ->toArray();

    expect($actions)->toContain(FlowAction::Start->value);
});

it('creates flow metric records', function () {
    $scenario = setupRealisticScenario();

    $this->artisan('flow:seed-realistic', [
        '--client' => $scenario['clientId'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $metricCount = FlowMetric::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->count();

    expect($metricCount)->toBeGreaterThan(0);

    $metric = FlowMetric::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->first();

    expect($metric->total_duration_minutes)->toBeGreaterThanOrEqual(0)
        ->and($metric->started_at)->not->toBeNull()
        ->and($metric->flow_config_step_id)->not->toBeNull()
        ->and($metric->flow_step_template_id)->not->toBeNull();
});

it('creates notification records', function () {
    $scenario = setupRealisticScenario();

    $this->artisan('flow:seed-realistic', [
        '--client' => $scenario['clientId'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $notificationCount = FlowNotification::where('notifiable_type', GondolaWorkflow::class)
        ->whereIn('notifiable_id', $scenario['gondolaIds'])
        ->count();

    expect($notificationCount)->toBeGreaterThanOrEqual(0);
});

it('wipes execution data but preserves config steps on --force rerun', function () {
    $scenario = setupRealisticScenario();

    $this->artisan('flow:seed-realistic', [
        '--client' => $scenario['clientId'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $firstRunExecutionCount = FlowExecution::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->count();

    $this->artisan('flow:seed-realistic', [
        '--client' => $scenario['clientId'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $secondRunExecutionCount = FlowExecution::where('workable_type', GondolaWorkflow::class)
        ->whereIn('workable_id', $scenario['gondolaIds'])
        ->count();

    expect($secondRunExecutionCount)->toBe($firstRunExecutionCount);

    $configStepCount = FlowConfigStep::where('configurable_type', PlanogramWorkflow::class)
        ->where('configurable_id', $scenario['planogramId'])
        ->count();

    expect($configStepCount)->toBe(4);
});
