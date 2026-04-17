<?php

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/_test/abort/{status}', function (int $status) {
        abort($status);
    })->middleware('web');
});

it('renders Inertia error page for 404', function (): void {
    $response = $this->get('/_test/abort/404');

    $response->assertStatus(404)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->has('status')
            ->where('status', 404)
        );
});

it('renders Inertia error page for 403', function (): void {
    $response = $this->get('/_test/abort/403');

    $response->assertStatus(403)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 403)
        );
});

it('renders Inertia error page for 401', function (): void {
    $response = $this->get('/_test/abort/401');

    $response->assertStatus(401)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 401)
        );
});

it('renders Inertia error page for 402', function (): void {
    $response = $this->get('/_test/abort/402');

    $response->assertStatus(402)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 402)
        );
});

it('renders Inertia error page for 429', function (): void {
    $response = $this->get('/_test/abort/429');

    $response->assertStatus(429)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 429)
        );
});

it('renders Inertia error page for 500', function (): void {
    $response = $this->get('/_test/abort/500');

    $response->assertStatus(500)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 500)
        );
});

it('renders Inertia error page for 503', function (): void {
    $response = $this->get('/_test/abort/503');

    $response->assertStatus(503)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 503)
        );
});

it('maps unmapped 4xx status codes to 404', function (): void {
    $response = $this->get('/_test/abort/410');

    $response->assertStatus(404)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 404)
        );
});

it('maps unmapped 5xx status codes to 500', function (): void {
    $response = $this->get('/_test/abort/502');

    $response->assertStatus(500)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 500)
        );
});

it('returns JSON for requests expecting JSON on 404', function (): void {
    $response = $this->getJson('/_test/abort/404');

    $response->assertStatus(404)
        ->assertJsonStructure(['message']);
});

it('returns JSON for requests expecting JSON on 403', function (): void {
    $response = $this->getJson('/_test/abort/403');

    $response->assertStatus(403)
        ->assertJsonStructure(['message']);
});

it('does not expose exception details for non-existent route', function (): void {
    $response = $this->get('/this-route-absolutely-does-not-exist-xyz');

    $response->assertStatus(404)
        ->assertInertia(fn ($page) => $page
            ->component('errors/Error')
            ->where('status', 404)
        );
});
