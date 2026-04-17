<?php

use App\Models\User;

/**
 * Testa que as rotas principais que usam o ResourceLayout/RaptorLayout
 * retornam respostas HTTP corretas para usuários autenticados.
 */
describe('RaptorLayout', function () {
    it('redireciona usuário não autenticado para login', function () {
        $response = $this->get('/dashboard');

        $response->assertRedirectToRoute('login');
    });

    it('retorna 200 no dashboard para usuário autenticado', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
    });
});
