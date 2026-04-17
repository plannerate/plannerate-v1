<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('renders the flow test page with the package kanban payload', function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    $user = User::factory()->create();

    actingAs($user);

    $response = $this->get(route('flow-test'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('FlowTest')
        ->where('title', 'Teste Flow Kanban (pacote)')
        ->has('board.steps', 3)
        ->has('board.executions.step-1', 2)
        ->has('filters.data', 1)
    );
});
