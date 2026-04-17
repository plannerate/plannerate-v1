<?php

use Callcocam\LaravelRaptorFlow\Models\FlowParticipant;
use Callcocam\LaravelRaptorFlow\Models\FlowPreset;
use Callcocam\LaravelRaptorFlow\Models\FlowPresetStep;
use Callcocam\LaravelRaptorFlow\Services\ResolvePresetDefaultUsersService;
use Callcocam\LaravelRaptorFlow\Services\ResolvePresetStepUsersService;
use Tests\TestCase;

uses(TestCase::class);

it('resolves default users from loaded preset steps without querying database', function () {
    config()->set('flow.features.resolve_preset_default_users', true);

    $presetStep = new FlowPresetStep([
        'workflow_step_template_id' => '01HTEMPLATE',
        'metadata' => [
            'default_users' => ['01HDEFAULT1', '01HDEFAULT2'],
        ],
    ]);

    $preset = new FlowPreset([
        'is_active' => true,
        'is_default' => true,
    ]);

    $preset->setRelation('steps', collect([$presetStep]));

    $service = new class(new ResolvePresetStepUsersService, $preset) extends ResolvePresetDefaultUsersService
    {
        public function __construct(
            ?ResolvePresetStepUsersService $resolvePresetStepUsersService,
            protected FlowPreset $preset,
        ) {
            parent::__construct($resolvePresetStepUsersService);
        }

        protected function resolveDefaultPreset(?string $workableType): ?FlowPreset
        {
            return $this->preset;
        }
    };

    expect($service->resolveForTemplate('01HTEMPLATE', 'App\\Models\\Workflow\\PlanogramWorkflow'))
        ->toBe(['01HDEFAULT1', '01HDEFAULT2']);
});

it('prioritizes preset step participants over metadata in default user resolution', function () {
    config()->set('flow.features.resolve_preset_default_users', true);

    $presetStep = new FlowPresetStep([
        'workflow_step_template_id' => '01HTEMPLATE2',
        'metadata' => [
            'default_users' => ['01HMETAUSER'],
        ],
    ]);

    $presetStep->setRelation('participants', collect([
        new FlowParticipant(['user_id' => '01HPARTICIPANT1']),
        new FlowParticipant(['user_id' => '01HPARTICIPANT2']),
    ]));

    $preset = new FlowPreset([
        'is_active' => true,
        'is_default' => true,
    ]);

    $preset->setRelation('steps', collect([$presetStep]));

    $service = new class(new ResolvePresetStepUsersService, $preset) extends ResolvePresetDefaultUsersService
    {
        public function __construct(
            ?ResolvePresetStepUsersService $resolvePresetStepUsersService,
            protected FlowPreset $preset,
        ) {
            parent::__construct($resolvePresetStepUsersService);
        }

        protected function resolveDefaultPreset(?string $workableType): ?FlowPreset
        {
            return $this->preset;
        }
    };

    expect($service->resolveForTemplate('01HTEMPLATE2'))
        ->toBe(['01HPARTICIPANT1', '01HPARTICIPANT2']);
});
