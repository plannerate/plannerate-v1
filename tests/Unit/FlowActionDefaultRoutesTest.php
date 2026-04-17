<?php

use Callcocam\LaravelRaptorFlow\Support\Actions\AbandonAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\ActionComponents;
use Callcocam\LaravelRaptorFlow\Support\Actions\AssignAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\MoveAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\NotesAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\PauseAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\ResumeAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\StartAction;
use Tests\TestCase;

uses(TestCase::class);

function expectedExecutionRoute(string $name): string
{
    $marker = '__FLOW_EXECUTION_PLACEHOLDER__';

    return str_replace($marker, '{id}', route($name, ['execution' => $marker]));
}

it('uses default execution routes for built-in flow actions', function (string $actionClass, string $segment) {
    $action = new $actionClass;

    expect($action->toArray())
        ->toMatchArray([
            'method' => 'post',
            'url' => expectedExecutionRoute("flow.execution.{$segment}"),
        ]);
})->with([
    [StartAction::class, 'start'],
    [MoveAction::class, 'move'],
    [PauseAction::class, 'pause'],
    [ResumeAction::class, 'resume'],
    [AssignAction::class, 'assign'],
    [AbandonAction::class, 'abandon'],
    [NotesAction::class, 'notes'],
]);

it('exposes canonical component names for built-in flow actions', function () {
    expect((new StartAction)->toArray()['component'])->toBe(ActionComponents::START)
        ->and((new PauseAction)->toArray()['component'])->toBe(ActionComponents::PAUSE)
        ->and((new ResumeAction)->toArray()['component'])->toBe(ActionComponents::RESUME)
        ->and((new AbandonAction)->toArray()['component'])->toBe(ActionComponents::ABANDON)
        ->and((new NotesAction)->toArray()['component'])->toBe(ActionComponents::NOTES)
        ->and((new MoveAction)->defaultComponent()->toArray()['component'])->toBe(ActionComponents::BUTTON)
        ->and(ActionComponents::forActionId('unknown'))->toBe(ActionComponents::BUTTON);
});
