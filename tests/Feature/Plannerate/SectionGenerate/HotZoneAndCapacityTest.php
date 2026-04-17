<?php

// FEATURE TEST - Testa hot zone e reordenação de sections
use App\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use App\Models\Plannerate\Category;
use App\Models\Plannerate\Client;
use App\Models\Plannerate\Gondola;
use App\Models\Plannerate\Planogram;
use App\Models\Plannerate\Product;
use App\Models\Plannerate\Sale;
use App\Models\Plannerate\Section;
use App\Models\Plannerate\Segment;
use App\Models\Plannerate\Shelf;
use App\Models\Plannerate\Store;
use App\Services\Plannerate\SectionGenerate\SectionPlanogramService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Hot zone implementation', function () {
    it('allocates high-score products at the right side for RTL flow', function () {
        // Setup: criar estrutura
        $client = Client::factory()->create();
        $store = Store::factory()->for($client)->create();
        $category = Category::factory()->create();
        $planogram = Planogram::factory()
            ->for($client)
            ->for($store)
            ->for($category)
            ->create();
        
        $gondola = Gondola::factory()
            ->for($planogram)
            ->create(['flow' => 'right_to_left']); // RTL

        $section = Section::factory()->for($gondola)->create(['width' => 200, 'height' => 200]);
        $shelf = Shelf::factory()->for($section)->create(['shelf_depth' => 40, 'shelf_position' => 0]);

        // Produtos com scores diferentes
        $productA = Product::factory()->for($category)->create([
            'name' => 'Produto A - Alto Score',
            'width' => 10,
            'depth' => 15,
            'height' => 20
        ]);
        $productB = Product::factory()->for($category)->create([
            'name' => 'Produto B - Médio Score',
            'width' => 10,
            'depth' => 15,
            'height' => 20
        ]);
        $productC = Product::factory()->for($category)->create([
            'name' => 'Produto C - Baixo Score',
            'width' => 10,
            'depth' => 15,
            'height' => 20
        ]);

        // Sales com vendas diferenciadas (score vem das vendas)
        Sale::factory()->for($store)->for($productA)->create(['quantity' => 1000]);
        Sale::factory()->for($store)->for($productB)->create(['quantity' => 500]);
        Sale::factory()->for($store)->for($productC)->create(['quantity' => 100]);

        $config = new AutoGenerateConfigDTO(
            categoryId: $category->id,
            strategy: 'sales',
            groupBySubcategory: false,
            minFacings: 1,
            maxFacings: 3,
            includeProductsWithoutSales: false,
            tableType: 'sales',
        );

        $service = app(SectionPlanogramService::class);
        $result = $service->generateBySections($gondola->id, $config);

        // Verificar: com RTL, produto A (maior score) deve estar mais à direita
        $segments = Segment::where('shelf_id', $shelf->id)
            ->with('layers.product')
            ->orderBy('position', 'desc') // maior position = mais à direita
            ->get();

        expect($segments)->toHaveCount(3);
        
        // Primeiro segment (maior position) deve ser produto A
        $firstProduct = $segments->first()->layers->first()->product;
        expect($firstProduct->name)->toContain('Produto A');
        
        // Último segment (menor position) deve ser produto C
        $lastProduct = $segments->last()->layers->first()->product;
        expect($lastProduct->name)->toContain('Produto C');
    });

    it('allocates high-score products at the left side for LTR flow', function () {
        $client = Client::factory()->create();
        $store = Store::factory()->for($client)->create();
        $category = Category::factory()->create();
        $planogram = Planogram::factory()
            ->for($client)
            ->for($store)
            ->for($category)
            ->create();
        
        $gondola = Gondola::factory()
            ->for($planogram)
            ->create(['flow' => 'left_to_right']); // LTR (padrão)

        $section = Section::factory()->for($gondola)->create(['width' => 200, 'height' => 200]);
        $shelf = Shelf::factory()->for($section)->create(['shelf_depth' => 40, 'shelf_position' => 0]);

        $productA = Product::factory()->for($category)->create([
            'name' => 'Produto A - Alto Score',
            'width' => 10,
            'depth' => 15,
            'height' => 20
        ]);
        $productB = Product::factory()->for($category)->create([
            'name' => 'Produto B - Baixo Score',
            'width' => 10,
            'depth' => 15,
            'height' => 20
        ]);

        Sale::factory()->for($store)->for($productA)->create(['quantity' => 1000]);
        Sale::factory()->for($store)->for($productB)->create(['quantity' => 100]);

        $config = new AutoGenerateConfigDTO(
            categoryId: $category->id,
            strategy: 'sales',
            groupBySubcategory: false,
            minFacings: 1,
            maxFacings: 3,
            includeProductsWithoutSales: false,
            tableType: 'sales',
        );

        $service = app(SectionPlanogramService::class);
        $result = $service->generateBySections($gondola->id, $config);

        $segments = Segment::where('shelf_id', $shelf->id)
            ->with('layers.product')
            ->orderBy('position') // menor position = mais à esquerda
            ->get();

        expect($segments)->toHaveCount(2);
        
        // Primeiro segment (menor position) deve ser produto A
        $firstProduct = $segments->first()->layers->first()->product;
        expect($firstProduct->name)->toContain('Produto A');
    });
});

describe('Section capacity ordering', function () {
    it('processes larger sections first to prevent monopolization', function () {
        $client = Client::factory()->create();
        $store = Store::factory()->for($client)->create();
        $category = Category::factory()->create();
        $planogram = Planogram::factory()
            ->for($client)
            ->for($store)
            ->for($category)
            ->create();
        
        $gondola = Gondola::factory()->for($planogram)->create();

        // Criar 3 sections com capacidades diferentes
        $smallSection = Section::factory()->for($gondola)->create([
            'width' => 50,  // capacidade: 50 × 100 = 5000
            'height' => 100,
        ]);
        $mediumSection = Section::factory()->for($gondola)->create([
            'width' => 100, // capacidade: 100 × 150 = 15000
            'height' => 150,
        ]);
        $largeSection = Section::factory()->for($gondola)->create([
            'width' => 200, // capacidade: 200 × 200 = 40000
            'height' => 200,
        ]);

        // Criar shelves para cada section
        Shelf::factory()->for($smallSection)->create(['shelf_depth' => 40]);
        Shelf::factory()->for($mediumSection)->create(['shelf_depth' => 40]);
        Shelf::factory()->for($largeSection)->create(['shelf_depth' => 40]);

        // Produtos ranqueados
        $products = collect();
        for ($i = 1; $i <= 10; $i++) {
            $product = Product::factory()->for($category)->create([
                'width' => 10,
                'depth' => 15,
                'height' => 20
            ]);
            Sale::factory()->for($store)->for($product)->create(['quantity' => (11 - $i) * 100]);
            $products->push($product);
        }

        $config = new AutoGenerateConfigDTO(
            categoryId: $category->id,
            strategy: 'sales',
            groupBySubcategory: false,
            minFacings: 1,
            maxFacings: 2,
            includeProductsWithoutSales: false,
            tableType: 'sales',
        );

        $service = app(SectionPlanogramService::class);
        $result = $service->generateBySections($gondola->id, $config);

        // Verificar: section grande deve ter sido processada primeiro
        // e ter recebido produtos de maior score
        $largeSegments = Segment::whereHas('shelf', fn($q) => 
            $q->where('section_id', $largeSection->id)
        )->with('layers.product')->get();

        $mediumSegments = Segment::whereHas('shelf', fn($q) => 
            $q->where('section_id', $mediumSection->id)
        )->with('layers.product')->get();

        $smallSegments = Segment::whereHas('shelf', fn($q) => 
            $q->where('section_id', $smallSection->id)
        )->with('layers.product')->get();

        // Section grande deve ter mais produtos alocados
        expect($largeSegments->count())->toBeGreaterThanOrEqual($mediumSegments->count());
        expect($mediumSegments->count())->toBeGreaterThanOrEqual($smallSegments->count());
        
        // Pelo menos alguns produtos alocados
        expect($result->totalAllocated)->toBeGreaterThan(0);
        expect($result->sectionsProcessed)->toBe(3);
    });
});
