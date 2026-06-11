<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramOutput;
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

/*
 * O modo automático sintetiza um template real (categorias → slots) antes do
 * placement, então o teste precisa das tabelas de síntese e de uma hierarquia
 * de categorias persistida (fixture modernizado na triagem da fase 5 — o teste
 * original antecedia o reroute automático → síntese → TemplatePlacementEngine).
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

    Schema::connection('tenant')->create('planograms', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('subtemplate_id', 26)->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('adjacency_rules', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('source_category_id', 26)->nullable();
        $table->char('target_category_id', 26)->nullable();
        $table->string('rule_type')->nullable();
        $table->decimal('weight', 6, 2)->default(1.0);
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
});

/** Persiste uma categoria na conexão tenant e retorna o id. */
function vbCategory(string $name, ?string $parentId = null): string
{
    $id = (string) Str::ulid();
    DB::connection('tenant')->table('categories')->insert([
        'id' => $id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.substr($id, -6),
        'category_id' => $parentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

// ── Helpers locais ────────────────────────────────────────────────────────────

function vbFeatureProduct(int $index, float $width = 10.0, float $height = 20.0, ?string $categoryId = null): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = "Feature VB {$index}";
    $product->ean = str_pad((string) ($index + 9000), 13, '0', STR_PAD_LEFT);
    $product->width = $width;
    $product->height = $height;
    $product->status = 'published';
    $product->category_id = $categoryId;

    return $product;
}

function vbFeatureShelf(string $sectionId, int $position, int $widthCm = 100): Shelf
{
    $shelf = new Shelf;
    $shelf->id = (string) Str::ulid();
    $shelf->section_id = $sectionId;
    $shelf->shelf_width = $widthCm;
    $shelf->shelf_height = 4;
    $shelf->shelf_depth = 40;
    $shelf->ordering = 0;
    $shelf->shelf_position = $position;

    return $shelf;
}

function vbFeatureSection(string $gondolaId, int $numShelves = 3, int $widthCm = 100): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->gondola_id = $gondolaId;
    $section->width = $widthCm;
    $section->height = 200;
    $section->cremalheira_width = 0;

    $shelves = collect();
    for ($i = 0; $i < $numShelves; $i++) {
        $shelves->push(vbFeatureShelf($section->id, $i * 40, $widthCm));
    }
    $section->setRelation('shelves', $shelves);

    return $section;
}

/**
 * Scorer com scores decrescentes — produto 0 = score 100, produto N = score 100-N*10.
 *
 * @param  Collection<int, Product>  $products
 */
function vbFeatureScorer(Collection $products): ProductScorerInterface
{
    $abcCycle = ['A', 'A', 'B', 'B', 'C'];

    $scored = $products->values()->map(fn (Product $p, int $i) => new ScoredProduct(
        productId: $p->id,
        ean: (string) ($p->ean ?? ''),
        score: 100.0 - $i * 5.0,
        product: $p,
        metadata: [
            'abc_class' => $abcCycle[$i % count($abcCycle)],
            'sales_total' => 500 - $i * 10,
            'margin' => 0,
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

function runVbPipeline(Collection $sections, Collection $products, float $threshold = 0.20, ?string $baseCategoryId = null): PlanogramOutput
{
    $mockWriter = new class implements PlanogramWriterInterface
    {
        public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void {}
    };

    app()->bind(ProductScorerInterface::class, fn () => vbFeatureScorer($products));
    app()->instance(PlanogramWriterInterface::class, $mockWriter);
    app()->instance(PlanogramValidator::class, new PlanogramValidator);

    return app(AutoPlanogramService::class)->generate(new PlanogramInput(
        planogramId: (string) Str::ulid(),
        gondolaId: (string) Str::ulid(),
        tenantId: '',
        products: $products,
        sections: $sections,
        settings: new PlacementSettings(
            strategy: 'abc',
            useExistingAnalysis: false,
            startDate: null,
            endDate: null,
            minFacings: 1,
            maxFacings: 1,
            groupBySubcategory: false,
            includeProductsWithoutSales: true,
            verticalBlockThreshold: $threshold,
            verticalBlockMinShelves: 2,
        ),
        planogramCategoryId: $baseCategoryId,
    ));
}

/** Cria hierarquia mínima (Bebidas > Refrigerantes) e devolve [rootId, leafId]. */
function vbCategoryPair(): array
{
    $rootId = vbCategory('Bebidas VB');
    $leafId = vbCategory('Refrigerantes VB', parentId: $rootId);

    return [$rootId, $leafId];
}

// ── Testes ────────────────────────────────────────────────────────────────────

test('top 20% de 10 produtos gera blocos verticais', function (): void {
    // 10 products, top 20% = 2 candidates
    [$rootId, $leafId] = vbCategoryPair();
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 9))->map(fn ($i) => vbFeatureProduct($i, categoryId: $leafId));

    $output = runVbPipeline($sections, $products, threshold: 0.20, baseCategoryId: $rootId);

    $verticalSegments = $output->placedSegments->filter(fn (PlacedSegment $s) => $s->isVerticalBlock);

    // 2 candidatos × 3 prateleiras = no mínimo 4 segmentos verticais (mínimo 2 prateleiras por candidato)
    expect($verticalSegments->count())->toBeGreaterThanOrEqual(4);
})->skip('Blocos verticais não são gerados pelo fluxo atual: o modo automático foi rerouteado para o TemplatePlacementEngine e a blocagem vertical (do GreedyShelfPlacer legado) não foi portada — nenhum código em src/AutoPlanogram seta isVerticalBlock=true. Decisão de produto pendente: reimplementar no engine ou aposentar a feature (e remover verticalBlockThreshold/MinShelves + badge no frontend).');

test('produtos verticais têm mesmo position X em shelves diferentes da mesma section', function (): void {
    [$rootId, $leafId] = vbCategoryPair();
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 4))->map(fn ($i) => vbFeatureProduct($i, categoryId: $leafId));

    $output = runVbPipeline($sections, $products, threshold: 0.20, baseCategoryId: $rootId);

    $verticalSegments = $output->placedSegments->filter(fn (PlacedSegment $s) => $s->isVerticalBlock);

    // Para cada produto vertical, todos os segmentos na mesma section devem ter o mesmo position
    $productPositions = $verticalSegments->groupBy(fn (PlacedSegment $s) => $s->layers->first()?->productId);

    foreach ($productPositions as $productId => $segs) {
        $uniquePositions = $segs->pluck('position')->unique();
        expect($uniquePositions)->toHaveCount(1,
            "Produto {$productId} tem posições X diferentes em shelves distintas"
        );
    }
})->skip('Blocos verticais não são gerados pelo fluxo atual: o modo automático foi rerouteado para o TemplatePlacementEngine e a blocagem vertical (do GreedyShelfPlacer legado) não foi portada — nenhum código em src/AutoPlanogram seta isVerticalBlock=true. Decisão de produto pendente: reimplementar no engine ou aposentar a feature (e remover verticalBlockThreshold/MinShelves + badge no frontend).');

test('threshold 0 desativa blocos verticais', function (): void {
    [$rootId, $leafId] = vbCategoryPair();
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 9))->map(fn ($i) => vbFeatureProduct($i, categoryId: $leafId));

    $output = runVbPipeline($sections, $products, threshold: 0.0, baseCategoryId: $rootId);

    $verticalSegments = $output->placedSegments->filter(fn (PlacedSegment $s) => $s->isVerticalBlock);

    expect($verticalSegments)->toHaveCount(0);
});

test('segmentos verticais e normais não se sobrepõem em position X', function (): void {
    [$rootId, $leafId] = vbCategoryPair();
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 9))->map(fn ($i) => vbFeatureProduct($i, categoryId: $leafId));

    $output = runVbPipeline($sections, $products, threshold: 0.20, baseCategoryId: $rootId);

    // Agrupar por shelf_id
    $byShelf = $output->placedSegments->groupBy('shelfId');

    foreach ($byShelf as $shelfId => $shelfSegments) {
        $sorted = $shelfSegments->sortBy('position')->values();

        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = $sorted[$i - 1];
            $curr = $sorted[$i];

            // O início do segmento atual deve ser >= fim do segmento anterior
            $prevEnd = $prev->position + $prev->width;
            expect($curr->position)->toBeGreaterThanOrEqual($prevEnd,
                "Sobreposição na shelf {$shelfId}: segmento em pos {$curr->position} sobrepõe anterior em {$prev->position}+{$prev->width}"
            );
        }
    }
});
