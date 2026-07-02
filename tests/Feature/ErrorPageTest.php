<?php

use Inertia\Testing\AssertableInertia as Assert;

/*
 * Garante que as telas de erro personalizadas do Plannerate (página Inertia 'Error')
 * substituem as telas padrão do Laravel/Symfony para os status HTTP tratados.
 * Configurado em bootstrap/app.php via $exceptions->respond().
 */

test('404 renders the custom Plannerate Error page', function () {
    $response = $this->get('/rota-que-nao-existe-'.uniqid());

    $response
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404));
});

test('error page responds with the matching HTTP status code', function () {
    $this->get('/outra-rota-inexistente-'.uniqid())
        ->assertStatus(404);
});
