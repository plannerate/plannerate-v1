<?php

use Callcocam\LaravelRaptorFlow\Models\FlowParticipant;
use Callcocam\LaravelRaptorFlow\Models\FlowPresetStep;

it('hydrates users accessor from metadata users and default_users', function () {
    $step = new FlowPresetStep([
        'metadata' => [
            'default_users' => ['01HUSER1', '01HUSER2', '01HUSER1', null, ''],
        ],
    ]);

    expect($step->users)->toBe(['01HUSER1', '01HUSER2']);
});

it('prioritizes participant users when relation is loaded', function () {
    $step = new FlowPresetStep([
        'metadata' => [
            'users' => ['01HMETAUSER1'],
        ],
    ]);

    $step->setRelation('participants', collect([
        new FlowParticipant(['user_id' => '01HPARTICIPANT1']),
        new FlowParticipant(['user_id' => '01HPARTICIPANT2']),
    ]));

    expect($step->users)->toBe(['01HPARTICIPANT1', '01HPARTICIPANT2']);
});
