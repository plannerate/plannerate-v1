<?php

use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;

it('hydrates users accessor from metadata suggested_users', function () {
    $template = new FlowStepTemplate([
        'metadata' => [
            'suggested_users' => ['01HUSER1', '01HUSER2', '01HUSER1', null, ''],
        ],
    ]);

    expect($template->users)->toBe(['01HUSER1', '01HUSER2']);
});

it('includes users in serialized output for form hydration', function () {
    $template = new FlowStepTemplate([
        'metadata' => [
            'suggested_users' => ['01HUSERA'],
        ],
    ]);

    expect($template->toArray())
        ->toHaveKey('users')
        ->and($template->toArray()['users'])->toBe(['01HUSERA']);
});
