<?php

use Callcocam\LaravelRaptorFlow\Services\ResolvePresetDefaultUsersService;
use Callcocam\LaravelRaptorFlow\Services\ResolveStepTemplateSuggestedUsersService;
use Callcocam\LaravelRaptorFlow\Support\Builders\FlowConfigStepPayloadBuilder;

it('builds step payload with users and explicit suggested responsible', function () {
    $builder = new FlowConfigStepPayloadBuilder;

    $payload = $builder->buildFromConfig([
        'workflow_step_template_id' => '01HSTEP',
        'responsible_role_id' => '01HROLE',
        'estimated_duration_days' => 5,
        'suggested_responsible_id' => '01HEXPLICIT',
        'users' => [['id' => '01HUSER1'], ['value' => '01HUSER2'], '01HUSER1'],
    ], 3, true);

    expect($payload)->toBe([
        'flow_step_template_id' => '01HSTEP',
        'order' => 3,
        'default_role_id' => '01HROLE',
        'estimated_duration_days' => 5,
        'users' => ['01HUSER1', '01HUSER2'],
        'suggested_responsible_id' => '01HEXPLICIT',
    ]);
});

it('uses first normalized user as suggested responsible when none is provided', function () {
    $builder = new FlowConfigStepPayloadBuilder;

    $payload = $builder->buildFromConfig([
        'workflow_step_template_id' => '01HSTEP2',
        'users' => '01HUSERA,01HUSERB',
    ], 1, true);

    expect($payload)->toBe([
        'flow_step_template_id' => '01HSTEP2',
        'order' => 1,
        'default_role_id' => null,
        'estimated_duration_days' => 2,
        'users' => ['01HUSERA', '01HUSERB'],
        'suggested_responsible_id' => '01HUSERA',
    ]);
});

it('does not include users or inferred suggested responsible when users sync is disabled', function () {
    $builder = new FlowConfigStepPayloadBuilder;

    $payload = $builder->buildFromConfig([
        'workflow_step_template_id' => '01HSTEP3',
        'users' => ['01HUSERX'],
    ], 9, false);

    expect($payload)->toBe([
        'flow_step_template_id' => '01HSTEP3',
        'order' => 9,
        'default_role_id' => null,
        'estimated_duration_days' => 2,
    ]);
});

it('returns null when template id is missing', function () {
    $builder = new FlowConfigStepPayloadBuilder;

    expect($builder->buildFromConfig([
        'estimated_duration_days' => 4,
    ], 1, true))->toBeNull();
});

it('uses preset default users when users are not provided', function () {
    $resolver = new class extends ResolvePresetDefaultUsersService
    {
        public function resolveForTemplate(string $flowStepTemplateId, ?string $workableType = null): array
        {
            expect($flowStepTemplateId)->toBe('01HPRESETSTEP');
            expect($workableType)->toBe('App\\Models\\Workflow\\PlanogramWorkflow');

            return ['01HPRESETUSER1', '01HPRESETUSER2'];
        }
    };

    $builder = new FlowConfigStepPayloadBuilder(null, $resolver);

    $payload = $builder->buildFromConfig([
        'workflow_step_template_id' => '01HPRESETSTEP',
    ], 2, true, 'App\\Models\\Workflow\\PlanogramWorkflow');

    expect($payload)->toBe([
        'flow_step_template_id' => '01HPRESETSTEP',
        'order' => 2,
        'default_role_id' => null,
        'estimated_duration_days' => 2,
        'users' => ['01HPRESETUSER1', '01HPRESETUSER2'],
        'suggested_responsible_id' => '01HPRESETUSER1',
    ]);
});

it('prioritizes template suggested users over preset fallback', function () {
    $templateResolver = new class extends ResolveStepTemplateSuggestedUsersService
    {
        public function resolveForTemplate(string $flowStepTemplateId): array
        {
            expect($flowStepTemplateId)->toBe('01HTEMPLATESTEP');

            return ['01HTEMPLATEUSER1', '01HTEMPLATEUSER2'];
        }
    };

    $presetResolver = new class extends ResolvePresetDefaultUsersService
    {
        public function resolveForTemplate(string $flowStepTemplateId, ?string $workableType = null): array
        {
            return ['01HPRESETUSER1'];
        }
    };

    $builder = new FlowConfigStepPayloadBuilder($templateResolver, $presetResolver);

    $payload = $builder->buildFromConfig([
        'workflow_step_template_id' => '01HTEMPLATESTEP',
    ], 4, true, 'App\\Models\\Workflow\\PlanogramWorkflow');

    expect($payload)->toBe([
        'flow_step_template_id' => '01HTEMPLATESTEP',
        'order' => 4,
        'default_role_id' => null,
        'estimated_duration_days' => 2,
        'users' => ['01HTEMPLATEUSER1', '01HTEMPLATEUSER2'],
        'suggested_responsible_id' => '01HTEMPLATEUSER1',
    ]);
});
