<?php

use App\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO;
use App\DTOs\Plannerate\SectionGenerate\SectionAllocationResultDTO;
use App\Models\Category;
use App\Models\Client;
use App\Models\Editor\Gondola;
use App\Models\Editor\Section;
use App\Models\Editor\Segment;
use App\Models\Editor\Shelf;
use App\Models\Planogram;
use App\Services\Plannerate\SectionGenerate\SectionPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SectionPersistenceService();
    
    // Setup básico de tenant/client
    $this->client = Client::factory()->create();
    config(['app.current_client_id' => $this->client->id]);
});

describe('SectionPersistenceService flow handling', function () {
    
    it('calculates position_x correctly for left_to_right flow', function () {
        // Criar estrutura: Planogram -> Gondola (flow left_to_right) -> Section -> Shelf
        $category = Category::factory()->create();
        $planogram = Planogram::factory()->create([
            'category_id' => $category->id,
            'client_id' => $this->client->id,
        ]);
        
        $gondola = Gondola::create([
            'id' => (string) Str::ulid(),
            'planogram_id' => $planogram->id,
            'name' => 'Test Gondola',
            'flow' => 'left_to_right',
        ]);

        $section = Section::create([
            'id' => (string) Str::ulid(),
            'gondola_id' => $gondola->id,
            'width' => 100,
        ]);

        $shelf = Shelf::create([
            'id' => (string) Str::ulid(),
            'section_id' => $section->id,
            'shelf_width' => 100,
            'shelf_height' => 30,
            'shelf_depth' => 40,
        ]);

        // Criar alocação com 3 produtos
        $allocation = [
            new SectionAllocationItemDTO(
                shelfId: $shelf->id,
                productId: '01prod000000000000000000001',
                facings: 2,
                productWidth: 10.0,
                productDepth: 20.0,
                productHeight: 25.0,
            ),
            new SectionAllocationItemDTO(
                shelfId: $shelf->id,
                productId: '01prod000000000000000000002',
                facings: 1,
                productWidth: 15.0,
                productDepth: 20.0,
                productHeight: 25.0,
            ),
            new SectionAllocationItemDTO(
                shelfId: $shelf->id,
                productId: '01prod000000000000000000003',
                facings: 3,
                productWidth: 8.0,
                productDepth: 20.0,
                productHeight: 25.0,
            ),
        ];

        $result = new SectionAllocationResultDTO(
            reasoning: 'Test allocation',
            allocation: $allocation,
            unallocated: [],
        );

        // Persistir
        $this->service->saveAllocation($section, $result);

        // Verificar segments criados
        $segments = Segment::where('shelf_id', $shelf->id)
            ->orderBy('ordering')
            ->get();

        expect($segments)->toHaveCount(3);

        // Verificar position_x (deve acumular da esquerda para direita)
        // Produto 1: position = 0, width = 10 * 2 = 20
        // Produto 2: position = 20, width = 15 * 1 = 15
        // Produto 3: position = 35, width = 8 * 3 = 24
        expect($segments[0]->position)->toBe(0)
            ->and($segments[1]->position)->toBe(20)
            ->and($segments[2]->position)->toBe(35);

        // Verificar ordering (deve ser 0, 1, 2 - não invertido)
        expect($segments[0]->ordering)->toBe(0)
            ->and($segments[1]->ordering)->toBe(1)
            ->and($segments[2]->ordering)->toBe(2);
    });

    it('reverses ordering for right_to_left flow', function () {
        // Criar estrutura com flow right_to_left
        $category = Category::factory()->create();
        $planogram = Planogram::factory()->create([
            'category_id' => $category->id,
            'client_id' => $this->client->id,
        ]);
        
        $gondola = Gondola::create([
            'id' => (string) Str::ulid(),
            'planogram_id' => $planogram->id,
            'name' => 'Test Gondola RTL',
            'flow' => 'right_to_left',
        ]);

        $section = Section::create([
            'id' => (string) Str::ulid(),
            'gondola_id' => $gondola->id,
            'width' => 100,
        ]);

        $shelf = Shelf::create([
            'id' => (string) Str::ulid(),
            'section_id' => $section->id,
            'shelf_width' => 100,
            'shelf_height' => 30,
            'shelf_depth' => 40,
        ]);

        // Criar alocação com 3 produtos
        $allocation = [
            new SectionAllocationItemDTO(
                shelfId: $shelf->id,
                productId: '01prod000000000000000000001',
                facings: 2,
                productWidth: 10.0,
                productDepth: 20.0,
                productHeight: 25.0,
            ),
            new SectionAllocationItemDTO(
                shelfId: $shelf->id,
                productId: '01prod000000000000000000002',
                facings: 1,
                productWidth: 15.0,
                productDepth: 20.0,
                productHeight: 25.0,
            ),
            new SectionAllocationItemDTO(
                shelfId: $shelf->id,
                productId: '01prod000000000000000000003',
                facings: 3,
                productWidth: 8.0,
                productDepth: 20.0,
                productHeight: 25.0,
            ),
        ];

        $result = new SectionAllocationResultDTO(
            reasoning: 'Test allocation RTL',
            allocation: $allocation,
            unallocated: [],
        );

        // Persistir
        $this->service->saveAllocation($section, $result);

        // Verificar segments criados
        $segments = Segment::where('shelf_id', $shelf->id)
            ->orderBy('ordering')
            ->get();

        expect($segments)->toHaveCount(3);

        // Verificar position_x (ainda acumula da esquerda, position não muda)
        expect($segments[0]->position)->toBe(0)
            ->and($segments[1]->position)->toBe(20)
            ->and($segments[2]->position)->toBe(35);

        // Verificar ordering (deve estar INVERTIDO: 2, 1, 0)
        expect($segments[0]->ordering)->toBe(2)
            ->and($segments[1]->ordering)->toBe(1)
            ->and($segments[2]->ordering)->toBe(0);
    });

    it('handles multiple shelves with different products', function () {
        $category = Category::factory()->create();
        $planogram = Planogram::factory()->create([
            'category_id' => $category->id,
            'client_id' => $this->client->id,
        ]);
        
        $gondola = Gondola::create([
            'id' => (string) Str::ulid(),
            'planogram_id' => $planogram->id,
            'name' => 'Multi Shelf Gondola',
            'flow' => 'right_to_left',
        ]);

        $section = Section::create([
            'id' => (string) Str::ulid(),
            'gondola_id' => $gondola->id,
            'width' => 100,
        ]);

        $shelf1 = Shelf::create([
            'id' => (string) Str::ulid(),
            'section_id' => $section->id,
            'shelf_width' => 100,
            'shelf_height' => 30,
        ]);

        $shelf2 = Shelf::create([
            'id' => (string) Str::ulid(),
            'section_id' => $section->id,
            'shelf_width' => 100,
            'shelf_height' => 30,
        ]);

        // Alocação em 2 shelves
        $allocation = [
            // Shelf 1 - 2 produtos
            new SectionAllocationItemDTO(
                shelfId: $shelf1->id,
                productId: '01prod000000000000000000001',
                facings: 1,
                productWidth: 10.0,
            ),
            new SectionAllocationItemDTO(
                shelfId: $shelf1->id,
                productId: '01prod000000000000000000002',
                facings: 1,
                productWidth: 10.0,
            ),
            // Shelf 2 - 3 produtos
            new SectionAllocationItemDTO(
                shelfId: $shelf2->id,
                productId: '01prod000000000000000000003',
                facings: 1,
                productWidth: 10.0,
            ),
            new SectionAllocationItemDTO(
                shelfId: $shelf2->id,
                productId: '01prod000000000000000000004',
                facings: 1,
                productWidth: 10.0,
            ),
            new SectionAllocationItemDTO(
                shelfId: $shelf2->id,
                productId: '01prod000000000000000000005',
                facings: 1,
                productWidth: 10.0,
            ),
        ];

        $result = new SectionAllocationResultDTO(
            reasoning: 'Multi shelf test',
            allocation: $allocation,
            unallocated: [],
        );

        $this->service->saveAllocation($section, $result);

        // Verificar shelf 1 (2 produtos, invertidos: ordering 1, 0)
        $shelf1Segments = Segment::where('shelf_id', $shelf1->id)
            ->orderBy('ordering')
            ->get();
        expect($shelf1Segments)->toHaveCount(2)
            ->and($shelf1Segments[0]->ordering)->toBe(0)
            ->and($shelf1Segments[1]->ordering)->toBe(1);

        // Verificar shelf 2 (3 produtos, invertidos: ordering 2, 1, 0)
        $shelf2Segments = Segment::where('shelf_id', $shelf2->id)
            ->orderBy('ordering')
            ->get();
        expect($shelf2Segments)->toHaveCount(3)
            ->and($shelf2Segments[0]->ordering)->toBe(0)
            ->and($shelf2Segments[1]->ordering)->toBe(1)
            ->and($shelf2Segments[2]->ordering)->toBe(2);
    });
});
