<?php

use App\Http\Middleware\EnforceWorkflowEnabled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

function fakeWorkflowTenant(array $settings = []): object
{
    return (object) [
        'id' => 'tenant-workflow-test',
        'settings' => $settings,
    ];
}

it('allows request when use_workflow is true', function () {
    app()->instance('current.tenant', fakeWorkflowTenant(['features' => ['use_workflow' => true]]));

    $middleware = new EnforceWorkflowEnabled;
    $request = Request::create('/flow/flows', 'GET');

    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('blocks request with 403 when use_workflow is false', function () {
    app()->instance('current.tenant', fakeWorkflowTenant(['features' => ['use_workflow' => false]]));

    $middleware = new EnforceWorkflowEnabled;
    $request = Request::create('/flow/flows', 'GET');

    expect(fn () => $middleware->handle($request, fn () => new Response('ok', 200)))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('blocks request with 403 when features key is absent', function () {
    app()->instance('current.tenant', fakeWorkflowTenant());

    $middleware = new EnforceWorkflowEnabled;
    $request = Request::create('/flow/flows', 'GET');

    expect(fn () => $middleware->handle($request, fn () => new Response('ok', 200)))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('passes through when tenant resolves to null (landlord context)', function () {
    app()->instance('current.tenant', null);

    $middleware = new EnforceWorkflowEnabled;
    $request = Request::create('/flow/flows', 'GET');

    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});
