<?php

use App\Models\Planogram;
use App\Models\User;
use App\Models\Workflow\PlanogramWorkflow;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowParticipant;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Services\FlowManager;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => base_path('database/migrations/clients'),
        '--realpath' => true,
        '--force' => true,
    ])->assertExitCode(0);
});

it('syncs configured users into flow participants and exposes them through planogram configs', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $planogram = Planogram::factory()->create();
    $configurable = PlanogramWorkflow::query()->findOrFail($planogram->id);

    $firstTemplate = FlowStepTemplate::create([
        'name' => 'Etapa 1',
        'slug' => Str::slug('Etapa 1 '.Str::ulid()),
        'suggested_order' => 1,
        'estimated_duration_days' => 2,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    $secondTemplate = FlowStepTemplate::create([
        'name' => 'Etapa 2',
        'slug' => Str::slug('Etapa 2 '.Str::ulid()),
        'suggested_order' => 2,
        'estimated_duration_days' => 3,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    app(FlowManager::class)->syncStepsFor($configurable, [
        [
            'flow_step_template_id' => $firstTemplate->id,
            'order' => 1,
            'estimated_duration_days' => 2,
            'users' => [$firstUser->id, $secondUser->id],
        ],
        [
            'flow_step_template_id' => $secondTemplate->id,
            'order' => 2,
            'estimated_duration_days' => 3,
            'users' => [$secondUser->id],
        ],
    ]);

    $participants = FlowParticipant::query()
        ->where('participable_type', FlowConfigStep::class)
        ->orderBy('participable_id')
        ->orderBy('user_id')
        ->get();

    expect($participants)->toHaveCount(3);

    $configs = $planogram->fresh()->configs;

    expect($configs)->toHaveCount(2)
        ->and($configs[0]->users)->toBe([$firstUser->id, $secondUser->id])
        ->and($configs[1]->users)->toBe([$secondUser->id]);
});

it('removes orphan participants when a step users list is cleared or removed', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $planogram = Planogram::factory()->create();
    $configurable = PlanogramWorkflow::query()->findOrFail($planogram->id);

    $firstTemplate = FlowStepTemplate::create([
        'name' => 'Etapa limpeza 1',
        'slug' => Str::slug('Etapa limpeza 1 '.Str::ulid()),
        'suggested_order' => 1,
        'estimated_duration_days' => 2,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    $secondTemplate = FlowStepTemplate::create([
        'name' => 'Etapa limpeza 2',
        'slug' => Str::slug('Etapa limpeza 2 '.Str::ulid()),
        'suggested_order' => 2,
        'estimated_duration_days' => 3,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    $manager = app(FlowManager::class);

    $manager->syncStepsFor($configurable, [
        [
            'flow_step_template_id' => $firstTemplate->id,
            'order' => 1,
            'users' => [$firstUser->id, $secondUser->id],
        ],
        [
            'flow_step_template_id' => $secondTemplate->id,
            'order' => 2,
            'users' => [$secondUser->id],
        ],
    ]);

    $manager->syncStepsFor($configurable, [
        [
            'flow_step_template_id' => $firstTemplate->id,
            'order' => 1,
            'users' => [],
        ],
    ]);

    $participants = FlowParticipant::query()
        ->where('participable_type', FlowConfigStep::class)
        ->get();

    expect($participants)->toHaveCount(0)
        ->and($planogram->fresh()->configs)->toHaveCount(1)
        ->and($planogram->fresh()->configs[0]->users)->toBe([]);
});

it('removes all steps and participants when synced with an empty array', function () {
    $firstUser = User::factory()->create();

    $planogram = Planogram::factory()->create();
    $configurable = PlanogramWorkflow::query()->findOrFail($planogram->id);

    $template = FlowStepTemplate::create([
        'name' => 'Etapa empty sync',
        'slug' => Str::slug('Etapa empty sync '.Str::ulid()),
        'suggested_order' => 1,
        'estimated_duration_days' => 2,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    app(FlowManager::class)->syncStepsFor($configurable, [
        [
            'flow_step_template_id' => $template->id,
            'order' => 1,
            'users' => [$firstUser->id],
        ],
    ]);

    expect($planogram->fresh()->configs)->toHaveCount(1);
    expect(FlowParticipant::where('participable_type', FlowConfigStep::class)->count())->toBe(1);

    // Sincronizar com array vazio deve remover todos os FlowConfigStep e participantes.
    app(FlowManager::class)->syncStepsFor($configurable, []);

    expect($planogram->fresh()->configs)->toHaveCount(0);
    expect(FlowParticipant::where('participable_type', FlowConfigStep::class)->count())->toBe(0);
});

it('preloads template suggested users when step has no participants', function () {
    $templateUserA = User::factory()->create();
    $templateUserB = User::factory()->create();

    $planogram = Planogram::factory()->create();
    $configurable = PlanogramWorkflow::query()->findOrFail($planogram->id);

    $template = FlowStepTemplate::create([
        'name' => 'Etapa com sugeridos do template',
        'slug' => Str::slug('Etapa com sugeridos do template '.Str::ulid()),
        'suggested_order' => 1,
        'estimated_duration_days' => 2,
        'is_required_by_default' => true,
        'is_active' => true,
        'metadata' => [
            'suggested_users' => [$templateUserA->id, $templateUserB->id],
        ],
    ]);

    app(FlowManager::class)->syncStepsFor($configurable, [
        [
            'flow_step_template_id' => $template->id,
            'order' => 1,
            'users' => [],
        ],
    ]);

    expect($planogram->fresh()->configs)->toHaveCount(1)
        ->and($planogram->fresh()->configs[0]->users)->toBe([
            (string) $templateUserA->id,
            (string) $templateUserB->id,
        ]);
});

it('does not sync participants when sync feature flag is disabled', function () {
    config()->set('flow.features.sync_config_step_participants', false);

    $firstUser = User::factory()->create();

    $planogram = Planogram::factory()->create();
    $configurable = PlanogramWorkflow::query()->findOrFail($planogram->id);

    $template = FlowStepTemplate::create([
        'name' => 'Etapa sem sync de participantes',
        'slug' => Str::slug('Etapa sem sync de participantes '.Str::ulid()),
        'suggested_order' => 1,
        'estimated_duration_days' => 2,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    app(FlowManager::class)->syncStepsFor($configurable, [
        [
            'flow_step_template_id' => $template->id,
            'order' => 1,
            'users' => [$firstUser->id],
        ],
    ]);

    expect(FlowParticipant::where('participable_type', FlowConfigStep::class)->count())->toBe(0)
        ->and($planogram->fresh()->configs)->toHaveCount(1)
        ->and($planogram->fresh()->configs[0]->users)->toBe([]);
});
