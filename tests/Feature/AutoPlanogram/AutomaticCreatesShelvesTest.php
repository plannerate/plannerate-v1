<?php

/**
 * Teste do prompt 36.1 — no modo automático o motor cria a estrutura de prateleiras.
 *
 * Cenário: gôndola criada SEM prateleiras (apenas o envelope físico das seções).
 * O motor deve, a partir do template sintetizado, criar as prateleiras dentro do envelope
 * e preenchê-las conforme a categoria. Módulo sem slot (categoria sem produto) não ganha prateleira.
 */

use App\Models\Category;
use App\Services\AutoPlanogram\AutoPlanogramService;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// ── Schema ───────────────────────────────────────────────────────────────────

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

    Schema::connection('tenant')->create('segments', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('shelf_id', 26)->nullable();
        $table->float('quantity')->default(0);
        $table->integer('ordering')->default(0);
        $table->float('position')->default(0);
        $table->float('width')->default(0);
        $table->float('distributed_width')->nullable();
        $table->boolean('is_vertical_block')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('layers', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('segment_id', 26)->nullable();
        $table->char('product_id', 26)->nullable();
        $table->string('ean')->nullable();
        $table->integer('quantity')->default(1);
        $table->float('height')->nullable();
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

    // Sections com envelope físico completo (height/base/furos) para o cálculo de posições
    Schema::connection('tenant')->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26)->nullable();
        $table->float('width')->default(100.0);
        $table->float('height')->default(180.0);
        $table->float('base_height')->default(10.0);
        $table->float('base_depth')->default(40.0);
        $table->float('cremalheira_width')->default(0.0);
        $table->float('hole_height')->default(2.0);
        $table->float('hole_spacing')->default(4.0);
        $table->integer('ordering')->default(1);
        $table->timestamps();
        $table->softDeletes();
    });

    // Shelves com todas as colunas físicas que o ShelfStructureService grava
    Schema::connection('tenant')->create('shelves', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('section_id', 26)->nullable();
        $table->string('code')->nullable();
        $table->integer('ordering')->default(1);
        $table->integer('shelf_position')->default(0);
        $table->float('shelf_width')->default(100.0);
        $table->float('shelf_height')->default(4.0);
        $table->float('shelf_depth')->default(40.0);
        $table->string('product_type')->default('normal');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('category_id', 26)->nullable();
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

// ── Helpers ───────────────────────────────────────────────────────────────────

function autoShelfCategory(string $name, ?string $parentId = null): Category
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

    return Category::find($id);
}

function autoShelfProduct(string $categoryId, float $width = 8.0, float $height = 25.0, string $name = 'Produto'): Product
{
    $p = new Product;
    $p->id = (string) Str::ulid();
    $p->name = $name;
    $p->ean = '789'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
    $p->width = $width;
    $p->height = $height;
    $p->status = 'published';
    $p->category_id = $categoryId;

    return $p;
}

/**
 * Persiste N seções SEM prateleiras (apenas envelope físico), como no fluxo automático.
 *
 * @return Collection<int, Section>
 */
function autoShelfSectionsWithoutShelves(string $gondolaId, int $numModules = 2): Collection
{
    return collect(range(1, $numModules))->map(function (int $i) use ($gondolaId): Section {
        $section = Section::create([
            'gondola_id' => $gondolaId,
            'width' => 100.0,
            'height' => 180.0,
            'base_height' => 10.0,
            'base_depth' => 40.0,
            'cremalheira_width' => 0.0,
            'hole_height' => 2.0,
            'hole_spacing' => 4.0,
            'ordering' => $i,
        ]);
        $section->setRelation('shelves', collect());

        return $section;
    });
}

function autoShelfBindMockScorer(array $abcMap = [], array $rawQtyMap = []): void
{
    app()->instance(ProductScorerInterface::class, new class($abcMap, $rawQtyMap) implements ProductScorerInterface
    {
        public function __construct(private array $abcMap, private array $rawQtyMap) {}

        public function score(Collection $products, PlacementSettings $settings): Collection
        {
            return $this->scoreOrNeutral($products, $settings);
        }

        public function scoreOrNeutral(Collection $products, PlacementSettings $settings): Collection
        {
            return $products->map(function (Product $p): ScoredProduct {
                $abc = $this->abcMap[$p->id] ?? null;

                return new ScoredProduct(
                    productId: $p->id,
                    ean: (string) ($p->ean ?? ''),
                    score: match ($abc) {
                        'A' => 0.9,
                        'B' => 0.6,
                        'C' => 0.3,
                        default => 0.5,
                    },
                    product: $p,
                    metadata: [
                        'score_type' => $abc !== null ? 'composite' : 'neutral',
                        'raw_quantity' => $this->rawQtyMap[$p->id] ?? 0.0,
                        'raw_margem' => 0.0,
                    ],
                );
            })->values();
        }
    });
}

function autoShelfGenerate(PlanogramInput $input): PlanogramOutput
{
    return app(AutoPlanogramService::class)->generate($input);
}

function autoShelfInput(string $gondolaId, string $planogramId, string $baseCategoryId, Collection $products, Collection $sections, array $abcMap): PlanogramInput
{
    return new PlanogramInput(
        planogramId: $planogramId,
        gondolaId: $gondolaId,
        tenantId: '',
        products: $products,
        sections: $sections,
        settings: new PlacementSettings(
            strategy: 'abc',
            useExistingAnalysis: false,
            startDate: null,
            endDate: null,
            minFacings: 1,
            maxFacings: 4,
            categoryId: $baseCategoryId,
            tenantId: null,
            abcClassMap: $abcMap,
        ),
        planogramCategoryId: $baseCategoryId,
    );
}

// ── Testes ────────────────────────────────────────────────────────────────────

test('motor cria 4 prateleiras somente nos módulos com demanda, deixa excedentes vazios', function (): void {
    // Cenário: 3 produtos de 8 cm cada em 2 subcategorias, prateleiras de 100 cm, 2 módulos físicos.
    // Demanda: 2 slots (1 por subcategoria) → ceil(2/4) = 1 módulo necessário.
    // Apenas o módulo 1 (seção 1) ganha 4 prateleiras; a seção 2 fica sem prateleiras.
    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();

    $root = autoShelfCategory('Bebidas');
    $refri = autoShelfCategory('Refrigerantes', parentId: $root->id);
    $sucos = autoShelfCategory('Sucos', parentId: $root->id);

    $refri1 = autoShelfProduct($refri->id, name: 'Refri 1');  // width=8.0
    $refri2 = autoShelfProduct($refri->id, name: 'Refri 2');  // width=8.0
    $suco1 = autoShelfProduct($sucos->id, name: 'Suco 1');    // width=8.0

    $products = collect([$refri1, $refri2, $suco1]);
    $abcMap = [$refri1->id => 'A', $refri2->id => 'A', $suco1->id => 'C'];

    autoShelfBindMockScorer($abcMap, [$refri1->id => 100.0, $refri2->id => 80.0, $suco1->id => 5.0]);

    $sections = autoShelfSectionsWithoutShelves($gondolaId, numModules: 2);

    // Pré-condição: nenhuma prateleira existe
    expect(Shelf::count())->toBe(0);

    autoShelfGenerate(autoShelfInput($gondolaId, $planogramId, $root->id, $products, $sections, $abcMap));

    // 1 módulo necessário × 4 prateleiras = 4 total; seção 2 fica sem prateleiras
    $orderedSections = Section::orderBy('ordering')->get();
    expect(Shelf::where('section_id', $orderedSections[0]->id)->count())->toBe(4);
    expect(Shelf::where('section_id', $orderedSections[1]->id)->count())->toBe(0);
    expect(Shelf::count())->toBe(4);

    // Produtos foram efetivamente alocados nas prateleiras criadas
    expect(Layer::whereNotNull('product_id')->count())->toBeGreaterThan(0);
    expect(Layer::where('product_id', $refri1->id)->count())->toBeGreaterThan(0);
});

test('motor usa 2 módulos quando demanda exige mais de 4 slots', function (): void {
    // Cenário: 5 subcategorias, cada uma com 1 produto de 40 cm → totalWidth = 200 cm.
    // Demanda per-subcat: ceil(40/100)=1 slot cada → total = 5 slots.
    // numModules = ceil(5/4) = 2 módulos → 2 seções × 4 prateleiras = 8 prateleiras.
    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();

    $root = autoShelfCategory('Bebidas');
    $sub1 = autoShelfCategory('Sub1', parentId: $root->id);
    $sub2 = autoShelfCategory('Sub2', parentId: $root->id);
    $sub3 = autoShelfCategory('Sub3', parentId: $root->id);
    $sub4 = autoShelfCategory('Sub4', parentId: $root->id);
    $sub5 = autoShelfCategory('Sub5', parentId: $root->id);

    $p1 = autoShelfProduct($sub1->id, width: 40.0, name: 'P1');
    $p2 = autoShelfProduct($sub2->id, width: 40.0, name: 'P2');
    $p3 = autoShelfProduct($sub3->id, width: 40.0, name: 'P3');
    $p4 = autoShelfProduct($sub4->id, width: 40.0, name: 'P4');
    $p5 = autoShelfProduct($sub5->id, width: 40.0, name: 'P5');

    $products = collect([$p1, $p2, $p3, $p4, $p5]);
    $abcMap = [$p1->id => 'A', $p2->id => 'B', $p3->id => 'C', $p4->id => 'C', $p5->id => 'C'];

    autoShelfBindMockScorer($abcMap, array_fill_keys($products->pluck('id')->all(), 10.0));

    // 3 seções físicas — apenas 2 serão usadas
    $sections = autoShelfSectionsWithoutShelves($gondolaId, numModules: 3);

    autoShelfGenerate(autoShelfInput($gondolaId, $planogramId, $root->id, $products, $sections, $abcMap));

    // 2 módulos × 4 prateleiras = 8; seção 3 fica vazia
    $orderedSections = Section::orderBy('ordering')->get();
    expect(Shelf::where('section_id', $orderedSections[0]->id)->count())->toBe(4);
    expect(Shelf::where('section_id', $orderedSections[1]->id)->count())->toBe(4);
    expect(Shelf::where('section_id', $orderedSections[2]->id)->count())->toBe(0);
    expect(Shelf::count())->toBe(8);

    expect(Layer::whereNotNull('product_id')->count())->toBeGreaterThan(0);
});

test('com 1 subcategoria e 1 módulo físico, cria exatamente 4 prateleiras (mínimo)', function (): void {
    // Cenário: 1 produto em 1 subcategoria → demanda = 1 slot → numModules = 1 → 4 prateleiras mínimas.
    // O mínimo de 4 prateleiras por módulo garante que o módulo seja aproveitado integralmente.
    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();

    $root = autoShelfCategory('Bebidas');
    $refri = autoShelfCategory('Refrigerantes', parentId: $root->id);

    $refri1 = autoShelfProduct($refri->id, name: 'Refri 1');
    $products = collect([$refri1]);
    $abcMap = [$refri1->id => 'A'];

    autoShelfBindMockScorer($abcMap, [$refri1->id => 100.0]);

    $sections = autoShelfSectionsWithoutShelves($gondolaId, numModules: 1);

    autoShelfGenerate(autoShelfInput($gondolaId, $planogramId, $root->id, $products, $sections, $abcMap));

    // Com 1 módulo e mínimo de 4 prateleiras, deve criar exatamente 4
    $sectionId = Section::first()->id;
    expect(Shelf::where('section_id', $sectionId)->count())->toBe(4);
    expect(Shelf::count())->toBe(4);

    // Produto alocado em alguma das prateleiras
    expect(Layer::where('product_id', $refri1->id)->count())->toBeGreaterThan(0);
});

test('regeração apaga prateleiras existentes e recria com mínimo de 4 por módulo', function (): void {
    // Comportamento "apagar tudo e regenerar": ao gerar automaticamente numa gôndola que já
    // tem prateleiras, o motor apaga as prateleiras existentes e cria novas (mín. 4 por módulo).
    // Isso garante que a estrutura resultante seja sempre determinística e dirigida pelo template.
    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();

    $root = autoShelfCategory('Bebidas');
    $refri = autoShelfCategory('Refrigerantes', parentId: $root->id);
    $refri1 = autoShelfProduct($refri->id, name: 'Refri 1');

    $products = collect([$refri1]);
    $abcMap = [$refri1->id => 'A'];
    autoShelfBindMockScorer($abcMap, [$refri1->id => 100.0]);

    // Seção já com 3 prateleiras criadas previamente (estrutura legada)
    $section = Section::create([
        'gondola_id' => $gondolaId,
        'width' => 100.0,
        'height' => 180.0,
        'base_height' => 10.0,
        'base_depth' => 40.0,
        'cremalheira_width' => 0.0,
        'hole_height' => 2.0,
        'hole_spacing' => 4.0,
        'ordering' => 1,
    ]);
    foreach (range(0, 2) as $pos) {
        $section->shelves()->create([
            'code' => 'SH-'.$pos,
            'ordering' => $pos + 1,
            'shelf_position' => $pos * 50,
            'shelf_width' => 100,
            'shelf_height' => 4,
            'shelf_depth' => 40,
            'product_type' => 'normal',
        ]);
    }
    $section->load('shelves');

    expect(Shelf::count())->toBe(3);

    autoShelfGenerate(autoShelfInput($gondolaId, $planogramId, $root->id, $products, collect([$section]), $abcMap));

    // Regeração apaga as 3 prateleiras antigas e cria 4 novas (mínimo por módulo)
    expect(Shelf::count())->toBe(4);
    expect(Shelf::where('section_id', $section->id)->count())->toBe(4);

    // Produto deve estar alocado nas novas prateleiras
    expect(Layer::where('product_id', $refri1->id)->count())->toBeGreaterThan(0);
});
