<?php

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
});

test('gerador de propostas renderiza para usuário landlord autenticado', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('landlord.proposal-generator.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('landlord/proposal-generator/Index'));
});

test('gerador de propostas exige autenticação', function () {
    $this->get(route('landlord.proposal-generator.index'))->assertRedirect();
});
