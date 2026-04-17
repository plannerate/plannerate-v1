<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('handles array to string conversion error gracefully when copying product', function () {
    // Cria um produto com dados complexos que podem causar o erro
    $product = Product::factory()->create([
        'name' => 'PILÃO DE MADEIRA C/ SOCADOR 10CM MAD LAR',
        'ean' => '7898622000552',
        'codigo_erp' => '26478',
    ]);

    // Cria um cliente de destino
    $targetClient = Client::factory()->create();

    $response = actingAs($this->user)
        ->postJson(route('tenant.products.execute'), [
            'record' => $product->id,
            'clients_cascading' => [
                'clients' => $targetClient->id,
            ],
        ]);

    // Deve retornar sucesso ou erro tratado, não crash
    expect($response->status())->toBeIn([200, 302, 422]);

    // Se for redirecionamento, verifica se tem mensagem
    if ($response->status() === 302) {
        expect(session()->has(['success', 'error']))->toBeTrue();
    }
});

it('successfully copies product with proper data preparation', function () {
    $product = Product::factory()->create([
        'name' => 'Produto Teste',
        'ean' => '1234567890123',
        'status' => 'published',
    ]);

    $targetClient = Client::factory()->create();

    $response = actingAs($this->user)
        ->postJson(route('tenant.products.execute'), [
            'record' => $product->id,
            'clients_cascading' => [
                'clients' => $targetClient->id,
            ],
        ]);

    expect($response->status())->toBe(302);
    expect(session('success'))->not->toBeNull();
});

it('prevents duplicate product copies by EAN', function () {
    $product = Product::factory()->create([
        'ean' => '1234567890123',
    ]);

    $targetClient = Client::factory()->create();

    // Primeira cópia - deve funcionar
    actingAs($this->user)
        ->postJson(route('tenant.products.execute'), [
            'record' => $product->id,
            'clients_cascading' => [
                'clients' => $targetClient->id,
            ],
        ]);

    // Segunda cópia - deve ser rejeitada
    $response = actingAs($this->user)
        ->postJson(route('tenant.products.execute'), [
            'record' => $product->id,
            'clients_cascading' => [
                'clients' => $targetClient->id,
            ],
        ]);

    expect($response->status())->toBe(302);
    expect(session('error'))->toContain('já existe');
});
