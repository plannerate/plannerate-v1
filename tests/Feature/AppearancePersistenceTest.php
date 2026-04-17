<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    app()->instance('current.tenant', (object) [
        'id' => 'tenant-test-id',
        'name' => 'Tenant Test',
        'slug' => 'tenant-test',
        'subdomain' => 'tenant-test',
        'domain' => 'tenant-test.localhost',
    ]);

    Route::middleware('web')->get('/test-appearance-persistence', function () {
        return Inertia::render('settings/Appearance');
    });
});

it('renders the dark class when the appearance cookie is dark', function () {
    $response = $this->withUnencryptedCookies(['appearance' => 'dark'])
        ->get('/test-appearance-persistence');

    $response->assertSuccessful();
    $response->assertSee('class="dark"', false);
    $response->assertSee("const appearance = 'dark';", false);
});
