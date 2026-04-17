<?php

use Callcocam\LaravelRaptorFlow\Models\FlowParticipant;
use Callcocam\LaravelRaptorFlow\Models\FlowPresetStep;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Services\ResolvePresetStepUsersService;
use Callcocam\LaravelRaptorFlow\Support\Builders\FlowPresetStepPayloadBuilder;

it('builds payload with resolved users and fallback suggested responsible', function () {
    $stepTemplate = new FlowStepTemplate([
        'name' => 'Template QA',
        'description' => 'Validacao final',
        'estimated_duration_days' => 3,
    ]);

    $presetStep = new FlowPresetStep([
        'workflow_step_template_id' => '01HTEMPLATE',
        'name' => null,
        'default_role_id' => '01HROLE',
        'suggested_responsible_id' => null,
        'estimated_duration_days' => null,
        'is_required' => true,
        'allow_skip' => false,
        'auto_assign_role' => false,
        'auto_assign_user' => false,
        'metadata' => [
            'users' => ['01HUSER1', '01HUSER2'],
        ],
    ]);

    $presetStep->setRelation('stepTemplate', $stepTemplate);

    $builder = new FlowPresetStepPayloadBuilder(new ResolvePresetStepUsersService);

    $payload = $builder->buildFromPresetStep($presetStep, 4);

    expect($payload)->toBe([
        'flow_step_template_id' => '01HTEMPLATE',
        'name' => 'Template QA',
        'description' => 'Validacao final',
        'order' => 4,
        'default_role_id' => '01HROLE',
        'suggested_responsible_id' => '01HUSER1',
        'estimated_duration_days' => 3,
        'is_required' => true,
        'is_active' => true,
        'allow_skip' => false,
        'auto_assign_role' => false,
        'auto_assign_user' => false,
        'users' => ['01HUSER1', '01HUSER2'],
    ]);
});

it('keeps explicit suggested responsible and prefers participants over metadata users', function () {
    $stepTemplate = new FlowStepTemplate([
        'name' => 'Template Execucao',
        'description' => 'Execucao da etapa',
        'estimated_duration_days' => 2,
    ]);

    $presetStep = new FlowPresetStep([
        'workflow_step_template_id' => '01HTEMPLATE2',
        'name' => 'Nome customizado',
        'default_role_id' => null,
        'suggested_responsible_id' => '01HEXPLICIT',
        'estimated_duration_days' => 5,
        'is_required' => false,
        'allow_skip' => true,
        'auto_assign_role' => true,
        'auto_assign_user' => true,
        'metadata' => [
            'users' => ['01HMETAUSER'],
        ],
    ]);

    $presetStep->setRelation('stepTemplate', $stepTemplate);
    $presetStep->setRelation('participants', collect([
        new FlowParticipant(['user_id' => '01HPARTICIPANT1']),
    ]));

    $builder = new FlowPresetStepPayloadBuilder(new ResolvePresetStepUsersService);

    $payload = $builder->buildFromPresetStep($presetStep, 1);

    expect($payload['name'])->toBe('Nome customizado')
        ->and($payload['suggested_responsible_id'])->toBe('01HEXPLICIT')
        ->and($payload['estimated_duration_days'])->toBe(5)
        ->and($payload['users'])->toBe(['01HPARTICIPANT1']);
});
