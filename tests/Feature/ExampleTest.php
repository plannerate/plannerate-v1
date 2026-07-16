<?php

test('home route redirects to the current host root', function () {
    $response = $this->get(route('home'));

    // "/" resolve para o dashboard do próprio host (tenant ou landlord),
    // evitando o redirect cross-origin que quebrava com CORS/403.
    $response->assertRedirect('/');
});
