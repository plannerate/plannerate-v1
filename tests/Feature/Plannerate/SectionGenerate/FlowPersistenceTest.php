<?php

// FEATURE TEST - USA DATABASE DE TESTES (NÃO AFETA PRODUÇÃO)
// O Sail automaticamente usa o banco 'testing' configurado no phpunit.xml

use App\Models\Plannerate\Client;
use App\Models\Plannerate\Gondola;
use App\Models\Plannerate\Planogram;
use App\Models\Plannerate\Product;
use App\Models\Plannerate\Sale;
use App\Models\Plannerate\Section;
use App\Models\Plannerate\Segment;
use App\Models\Plannerate\Shelf;
use App\Models\Plannerate\Store;
use App\Services\Plannerate\SectionGenerate\SectionPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class); // Reseta apenas banco de TESTES

describe('Flow and position_x calculation', function () {
    it('calculates position_x for left_to_right flow', function () {
        // Setup: criar estrutura mínima
        $client = Client::factory()->create();
        $store = Store::factory()->for($client)->create();
        $planogram = Planogram::factory()->for($client)->for($store)->create();
        
        $gondola = Gondola::factory()
            ->for($planogram)
            ->create(['flow' => 'left_to_right']);

        $section = Section::factory()->for($gondola)->create(['width' => 200]);
        $shelf = Shelf::factory()->for($section)->create(['shelf_depth' => 40]);

        // Produtos com larguras diferentes
        $product1 = Product::factory()->create(['width' => 10, 'depth' => 15]);
        $product2 = Product::factory()->create(['width' => 20, 'depth' => 15]);

        // Criar sales para os produtos
        Sale::factory()->for($store)->for($product1)->create();
        Sale::factory()->for($store)->for($product2)->create();

        // Items para alocar
        $items = [
            new \App\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO(
                productId: $product1->id,
                facings: 2, // 2 faces * 10cm = 20cm
                shelfId: $shelf->id,
                productWidth: 10.0,
                productDepth: 15.0,
                productHeight: 25.0
            ),
            new \App\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO(
                productId: $product2->id,
                facings: 3, // 3 faces * 20cm = 60cm
                shelfId: $shelf->id,
                productWidth: 20.0,
                productDepth: 15.0,
                productHeight: 25.0
            ),
        ];

        // Executar persistence
        $service = new SectionPersistenceService();
        $result = $service->saveAllocation($gondola, $items);

        // Verificar: position_x deve ser cumulativo
        $segments = Segment::where('shelf_id', $shelf->id)
            ->orderBy('position')
            ->get();

        expect($segments)->toHaveCount(2);
        
        // Primeiro produto: position = 0
        expect($segments[0]->position)->toBe(0);
        
        // Segundo produto: position = 20 (acumulado do primeiro)
        expect($segments[1]->position)->toBe(20);
    });

    it('reverses ordering for right_to_left flow', function () {
        $client = Client::factory()->create();
        $store = Store::factory()->for($client)->create();
        $planogram = Planogram::factory()->for($client)->for($store)->create();
        
        $gondola = Gondola::factory()
            ->for($planogram)
            ->create(['flow' => 'right_to_left']); // DIREITA PARA ESQUERDA

        $section = Section::factory()->for($gondola)->create(['width' => 200]);
        $shelf = Shelf::factory()->for($section)->create(['shelf_depth' => 40]);

        $product1 = Product::factory()->create(['width' => 10, 'depth' => 15]);
        $product2 = Product::factory()->create(['width' => 20, 'depth' => 15]);

        Sale::factory()->for($store)->for($product1)->create();
        Sale::factory()->for($store)->for($product2)->create();

        $items = [
            new \App\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO(
                productId: $product1->id,
                facings: 2,
                shelfId: $shelf->id,
                productWidth: 10.0,
                productDepth: 15.0,
                productHeight: 25.0
            ),
            new \App\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO(
                productId: $product2->id,
                facings: 3,
                shelfId: $shelf->id,
                productWidth: 20.0,
                productDepth: 15.0,
                productHeight: 25.0
            ),
        ];

        $service = new SectionPersistenceService();
        $result = $service->saveAllocation($gondola, $items);

        // Verificar: ordenação deve ser invertida
        $segments = Segment::where('shelf_id', $shelf->id)
            ->orderBy('position')
            ->get();

        expect($segments)->toHaveCount(2);
        
        // Com RTL, o segundo produto vem primeiro na ordenação visual
        expect($segments[0]->product_id)->toBe($product2->id);
        expect($segments[1]->product_id)->toBe($product1->id);
    });
});
