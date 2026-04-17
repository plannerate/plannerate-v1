<?php

use App\Http\Controllers\Workflow\GondolaWorkflowController;
use Inertia\Response;

test('controller exists and exposes only the workflow detail endpoint', function () {
    expect(class_exists(GondolaWorkflowController::class))->toBeTrue();

    $reflection = new ReflectionClass(GondolaWorkflowController::class);

    expect($reflection->hasMethod('show'))->toBeTrue();

    $showMethod = $reflection->getMethod('show');
    $returnType = $showMethod->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe(Response::class);

    $removedProxyMethods = [
        'start',
        'move',
        'reassign',
        'pause',
        'resume',
        'updateNotes',
        'abandon',
    ];

    foreach ($removedProxyMethods as $methodName) {
        expect($reflection->hasMethod($methodName))->toBeFalse("Method {$methodName} should not exist");
    }
});
