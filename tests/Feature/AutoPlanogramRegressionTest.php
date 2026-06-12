<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\PlanogramWriterInterface;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\ProductScorerInterface;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\PlanogramValidator;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Testes de regressão estrutural do AutoPlanogramService (Fase 1).
 *
 * Valida que o pipeline produz PlacedSegments com a estrutura correta para
 * 3 tamanhos de fixture: pequena, médio e grande.
 *
 * O PlanogramWriter é mockado para isolar o pipeline da camada de persistência.
 * O scorer é mockado para evitar dependência de dados de vendas reais.
 *
 * Nota: o modo automático sempre passa pelo AutoTemplateSynthesisOrchestrator,
 * portanto é necessária infraestrutura mínima de banco (categorias, templates, etc.).
 * Os testes criam uma categoria-raiz em cada execução para satisfazer essa dependência.
 */

// ── Schema mínimo para testes de regressão ────────────────────────────────────

/**
 * Recria as tabelas mínimas necessárias para o pipeline de geração automática.
 * É chamado em cada beforeEach para garantir isolamento entre testes.
 */
beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('categories', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('category_id', 26)->nullable()->index();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('status')->default('active');
        $table->integer('hierarchy_position')->nullable();
        $table->string('role')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_templates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('code');
        $table->string('name');
        $table->string('department');
        $table->char('category_id', 26)->nullable();
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(false);
        $table->char('created_by', 26)->nullable();
        $table->string('origin')->nullable();
        $table->char('source_gondola_id', 26)->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_subtemplates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('template_id', 26);
        $table->string('code');
        $table->unsignedTinyInteger('num_modules');
        $table->boolean('is_active')->default(true);
        $table->string('hot_zone_priority')->nullable();
        $table->string('cold_zone_priority')->nullable();
        $table->string('flow_direction')->nullable();
        $table->string('layout_orientation')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_template_slots', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('subtemplate_id', 26);
        $table->char('category_id', 26)->nullable();
        $table->unsignedTinyInteger('module_number');
        $table->unsignedTinyInteger('shelf_order');
        $table->unsignedTinyInteger('min_facings')->nullable();
        $table->unsignedSmallInteger('max_facings')->nullable();
        $table->json('visual_criteria')->nullable();
        $table->string('role_override')->nullable();
        $table->string('facing_expansion')->nullable();
        $table->boolean('use_target_stock')->default(false);
        $table->string('space_fallback')->nullable();
        $table->unsignedTinyInteger('max_share_per_sku')->nullable();
        $table->unsignedTinyInteger('max_share_per_brand')->nullable();
        $table->unsignedTinyInteger('max_share_per_subcategory')->nullable();
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->unsignedSmallInteger('priority')->default(1);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('shelves', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('section_id', 26)->nullable();
        $table->string('code')->nullable();
        $table->integer('ordering')->default(0);
        $table->integer('shelf_position')->default(0);
        $table->float('shelf_width')->default(100.0);
        $table->float('shelf_height')->default(4.0);
        $table->float('shelf_depth')->default(40.0);
        $table->string('product_type')->default('normal');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_rejected_products', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->char('gondola_id', 26)->nullable();
        $table->char('product_id', 26)->nullable();
        $table->string('product_name')->nullable();
        $table->string('ean')->nullable();
        $table->string('image_url')->nullable();
        $table->float('product_width')->nullable();
        $table->float('product_height')->nullable();
        $table->string('rejection_reason')->nullable();
        $table->char('slot_id', 26)->nullable();
        $table->string('category_name')->nullable();
        $table->char('category_id', 26)->nullable();
        $table->integer('module_number')->nullable();
        $table->integer('shelf_order')->nullable();
        $table->text('rejected_shelf_orders')->nullable();
        $table->timestamps();
    });

    Schema::connection('tenant')->create('planograms', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('subtemplate_id', 26)->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_gondola_slot_overrides', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26);
        $table->char('category_id', 26)->nullable();
        $table->unsignedTinyInteger('min_facings')->nullable();
        $table->unsignedSmallInteger('max_facings')->nullable();
        $table->string('price_order')->nullable();
        $table->string('size_order')->nullable();
        $table->string('brand_exposure')->nullable();
        $table->string('flavor_exposure')->nullable();
        $table->string('space_fallback')->nullable();
        $table->string('facing_expansion')->nullable();
        $table->boolean('use_target_stock')->nullable();
        $table->string('role_override')->nullable();
        $table->unsignedTinyInteger('max_share_per_sku')->nullable();
        $table->unsignedTinyInteger('max_share_per_brand')->nullable();
        $table->unsignedTinyInteger('max_share_per_subcategory')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

// ── Factories em memória ─────────────────────────────────────────────────────

/**
 * Cria um produto sintético sem persistir no banco.
 * O category_id deve ser definido pelo chamador antes de usar no pipeline.
 */
function fakeProduct(int $index, string $tenantId = 'tenant-test'): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->tenant_id = $tenantId;
    $product->name = "Produto {$index}";
    $product->ean = str_pad((string) ($index + 1000), 13, '0', STR_PAD_LEFT);
    $product->codigo_erp = "PROD{$index}";
    $product->width = 10;
    $product->height = 25;
    $product->depth = 30;
    $product->status = 'published';

    return $product;
}

/**
 * Cria uma shelf sintética sem persistir no banco.
 */
function fakeShelf(string $sectionId, int $index, int $widthCm = 100): Shelf
{
    $shelf = new Shelf;
    $shelf->id = (string) Str::ulid();
    $shelf->section_id = $sectionId;
    $shelf->shelf_width = $widthCm;
    $shelf->shelf_height = 4;
    $shelf->shelf_depth = 40;
    $shelf->ordering = $index;
    $shelf->shelf_position = 30 + ($index * 34);

    return $shelf;
}

/**
 * Cria uma section sintética com shelves sem persistir no banco.
 */
function fakeSection(string $gondolaId, int $numShelves, int $widthCm = 100): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->gondola_id = $gondolaId;
    $section->width = $widthCm;
    $section->height = 200;
    $section->cremalheira_width = 0;

    $shelves = collect();
    for ($i = 0; $i < $numShelves; $i++) {
        $shelves->push(fakeShelf($section->id, $i, $widthCm));
    }

    // Injeta relação carregada sem query
    $section->setRelation('shelves', $shelves);

    return $section;
}

/**
 * Cria um scorer mockado com scores decrescentes.
 *
 * @param  Collection<int, Product>  $products
 */
function fakeScorer(Collection $products): ProductScorerInterface
{
    $abcCycle = ['A', 'A', 'B', 'B', 'C'];

    $scored = $products->values()->map(fn (Product $p, int $i) => new ScoredProduct(
        productId: $p->id,
        ean: (string) ($p->ean ?? ''),
        score: 100.0 - $i * 5.0,
        product: $p,
        metadata: [
            'abc_class' => $abcCycle[$i % count($abcCycle)],
            'sales_total' => 1000 - $i * 50,
            'margin' => 200 - $i * 10,
            'target_stock' => null,
            'safety_stock' => null,
        ],
    ));

    return new class($scored) implements ProductScorerInterface
    {
        public function __construct(private readonly Collection $scored) {}

        public function score(Collection $products, PlacementSettings $settings): Collection
        {
            return $this->scored;
        }

        public function scoreOrNeutral(Collection $products, PlacementSettings $settings): Collection
        {
            return $this->scored;
        }
    };
}

/**
 * Cria um PlanogramInput completo a partir de sections e products sintéticos.
 *
 * @param  Collection<int, Section>  $sections
 * @param  Collection<int, Product>  $products
 */
function buildTestInput(Collection $sections, Collection $products, string $planogramCategoryId = ''): PlanogramInput
{
    return new PlanogramInput(
        planogramId: (string) Str::ulid(),
        gondolaId: (string) Str::ulid(),
        tenantId: 'tenant-test',
        products: $products,
        sections: $sections,
        settings: new PlacementSettings(
            strategy: 'abc',
            useExistingAnalysis: false,
            startDate: null,
            endDate: null,
            minFacings: 1,
            maxFacings: 3,
            groupBySubcategory: false,
            includeProductsWithoutSales: true,
            categoryId: $planogramCategoryId ?: null,
        ),
        planogramCategoryId: $planogramCategoryId ?: null,
    );
}

/**
 * Executa o pipeline com Writer mockado, retornando os PlacedSegments capturados.
 *
 * Cria uma categoria-raiz no banco em cada execução (necessário para o
 * AutoTemplateSynthesisOrchestrator encontrar a categoria pelo ID).
 * Atribui o category_id dos produtos a essa categoria-raiz para que
 * o placement engine os associe aos slots sintetizados.
 *
 * @param  Collection<int, Section>  $sections
 * @param  Collection<int, Product>  $products
 * @return Collection<int, PlacedSegment>
 */
function runPipelineWithMockedWriter(Collection $sections, Collection $products): Collection
{
    // Cria uma categoria-raiz para satisfazer a dependência do orchestrator.
    // Produtos são atribuídos a essa raiz (categoria-folha → modo leaf do orchestrator).
    $rootCatId = (string) Str::ulid();
    DB::connection('tenant')->table('categories')->insert([
        'id' => $rootCatId,
        'name' => 'Root Category',
        'slug' => 'root-category-'.substr($rootCatId, -6),
        'category_id' => null,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Vincula todos os produtos à categoria-raiz para que o placement engine
    // encontre candidatos nos slots sintetizados (category_id = rootCatId).
    $products->each(function (Product $p) use ($rootCatId): void {
        $p->category_id = $rootCatId;
    });

    $capturedSegments = collect();

    $mockWriter = new class($capturedSegments) implements PlanogramWriterInterface
    {
        public function __construct(private Collection &$captured) {}

        public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void
        {
            foreach ($placedSegments as $seg) {
                $this->captured->push($seg);
            }
        }
    };

    app()->bind(ProductScorerInterface::class, fn () => fakeScorer($products));
    app()->instance(PlanogramWriterInterface::class, $mockWriter);
    app()->instance(PlanogramValidator::class, new PlanogramValidator);

    app(AutoPlanogramService::class)->generate(buildTestInput($sections, $products, $rootCatId));

    return $capturedSegments;
}

// ── Fixtures ─────────────────────────────────────────────────────────────────

dataset('fixtures', [
    'pequena (1 section, 2 shelves, 3 produtos)' => [
        fn () => collect([fakeSection('g1', 2, 100)]),
        fn () => collect(array_map(fn ($i) => fakeProduct($i), range(0, 2))),
    ],
    'média (2 sections, 5 shelves, 6 produtos)' => [
        fn () => collect([fakeSection('g2', 3, 100), fakeSection('g2', 2, 100)]),
        fn () => collect(array_map(fn ($i) => fakeProduct($i), range(0, 5))),
    ],
    'grande (3 sections, 10 shelves, 12 produtos)' => [
        fn () => collect([fakeSection('g3', 4, 150), fakeSection('g3', 3, 150), fakeSection('g3', 3, 150)]),
        fn () => collect(array_map(fn ($i) => fakeProduct($i), range(0, 11))),
    ],
]);

// ── Testes ───────────────────────────────────────────────────────────────────

test(
    'pipeline produz segmentos não-vazios',
    function (Collection $sections, Collection $products): void {
        $segments = runPipelineWithMockedWriter($sections, $products);

        expect($segments)->not->toBeEmpty();
    }
)->with('fixtures')->group('regression');

test(
    'cada segmento tem exatamente uma layer',
    function (Collection $sections, Collection $products): void {
        $segments = runPipelineWithMockedWriter($sections, $products);

        $segments->each(fn (PlacedSegment $s) => expect($s->layers)->toHaveCount(1));
    }
)->with('fixtures')->group('regression');

test(
    'ordering dos segmentos é sequencial por shelf (começa em 0)',
    function (Collection $sections, Collection $products): void {
        $segments = runPipelineWithMockedWriter($sections, $products);

        $shelfIds = $segments->pluck('shelfId')->unique();

        $shelfIds->each(function (string $shelfId) use ($segments): void {
            $orderings = $segments
                ->where('shelfId', $shelfId)
                ->sortBy('ordering')
                ->pluck('ordering')
                ->values();

            expect($orderings->first())->toBe(0);

            for ($i = 1; $i < $orderings->count(); $i++) {
                expect($orderings[$i])->toBe($orderings[$i - 1] + 1);
            }
        });
    }
)->with('fixtures')->group('regression');

test(
    'nenhum segmento excede a largura da shelf',
    function (Collection $sections, Collection $products): void {
        // O mapa é construído APÓS o pipeline porque ensureShelvesForSynthesizedTemplate
        // substitui as prateleiras em memória pelas prateleiras criadas no banco.
        $segments = runPipelineWithMockedWriter($sections, $products);

        $shelfWidthMap = $sections
            ->flatMap(fn (Section $s) => $s->shelves->map(fn (Shelf $sh) => [$sh->id => $sh->shelf_width]))
            ->collapse();

        $groupedByShelf = $segments->groupBy('shelfId');

        $groupedByShelf->each(function (Collection $shelfSegments, string $shelfId) use ($shelfWidthMap): void {
            $totalWidth = $shelfSegments->sum('width');
            $availableWidth = $shelfWidthMap[$shelfId] ?? PHP_INT_MAX;

            expect($totalWidth)->toBeLessThanOrEqual($availableWidth);
        });
    }
)->with('fixtures')->group('regression');

test(
    'pipeline é determinístico — duas execuções produzem a mesma estrutura',
    function (Collection $sections, Collection $products): void {
        // Nota: cada execução recria as prateleiras (novas ULIDs), portanto shelfId
        // difere entre runs. Verificamos contagem, ordering e width — propriedades
        // estruturais independentes dos IDs gerados.
        $first = runPipelineWithMockedWriter($sections, $products);
        $second = runPipelineWithMockedWriter($sections, $products);

        expect($second->count())->toBe($first->count());

        $first->each(function (PlacedSegment $seg, int $idx) use ($second): void {
            $other = $second[$idx];
            expect($seg->ordering)->toBe($other->ordering)
                ->and($seg->width)->toBe($other->width);
        });
    }
)->with('fixtures')->group('regression');
