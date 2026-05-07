<?php

use App\Models\UsefulLink;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());
});

test('authenticated user can list useful links', function () {
    $response = $this->get(route('landlord.useful-links.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/useful-links/Index')
            ->has('useful_links.data'));
});

test('authenticated user can create update and delete useful link', function () {
    $createResponse = $this->post(route('landlord.useful-links.store'), [
        'name' => 'Portal de Treinamento',
        'url' => 'https://example.com/training',
        'logo' => 'https://example.com/logo.png',
        'description' => 'Treinamentos do time',
        'show_on_tenant_dashboard' => '1',
    ]);

    $createResponse->assertRedirect(route('landlord.useful-links.index'));

    $usefulLink = UsefulLink::query()->where('url', 'https://example.com/training')->firstOrFail();

    $this->assertDatabaseHas('useful_links', [
        'id' => $usefulLink->id,
        'name' => 'Portal de Treinamento',
        'show_on_tenant_dashboard' => 1,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.useful-links.update', $usefulLink), [
        'name' => 'Portal de Suporte',
        'url' => 'https://example.com/support',
        'logo' => 'https://example.com/support.png',
        'description' => 'Atendimento e suporte',
        'show_on_tenant_dashboard' => '0',
    ]);

    $updateResponse->assertRedirect(route('landlord.useful-links.index'));

    $this->assertDatabaseHas('useful_links', [
        'id' => $usefulLink->id,
        'name' => 'Portal de Suporte',
        'url' => 'https://example.com/support',
        'show_on_tenant_dashboard' => 0,
    ], 'landlord');

    $deleteResponse = $this->delete(route('landlord.useful-links.destroy', $usefulLink));

    $deleteResponse->assertRedirect(route('landlord.useful-links.index'));

    $this->assertSoftDeleted('useful_links', [
        'id' => $usefulLink->id,
    ], 'landlord');
});
