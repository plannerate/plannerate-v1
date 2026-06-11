<?php

/**
 * Teste E2E do modo automático — pendência 33.5.
 *
 * Cobre o pipeline completo: categoria do planograma → síntese de template →
 * posicionamento via TemplatePlacementEngine → persistência.
 *
 * Cenário base:
 * - Gôndola 2 sections × 4 prateleiras, 100 cm de largura cada.
 * - Categoria-base "Bebidas" com 3 subcategorias: Refrigerantes (A), Sucos, Chás (C).
 * - ~10 produtos com width/height válidos; alguns com venda, alguns sem.
 * - Produto "Wide" (55 cm > 50 cm de largura de prateleira → 1 frente já não cabe) → forçosamente rejeitado.
 * - Produto "Direto" pendurado na própria categoria selecionada → descartado silenciosamente.
 */

use App\Models\Category;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramOutput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\ProductScorerInterface;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramRejectedProduct;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    Schema::connection('tenant')->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->float('width')->default(100.0);
        $table->float('cremalheira_width')->default(0.0);
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

/**
 * Persiste uma categoria na conexão tenant e retorna o model.
 */
function autoE2eCategory(string $name, ?string $parentId = null): Category
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

/**
 * Cria um Product em memória (sem persistência) com os dados mínimos para o engine.
 */
function autoE2eProduct(
    string $categoryId,
    float $width = 8.0,
    float $height = 30.0,
    string $name = 'Produto',
    ?string $id = null,
): Product {
    $p = new Product;
    $p->id = $id ?? (string) Str::ulid();
    $p->name = $name;
    $p->ean = '789'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
    $p->width = $width;
    $p->height = $height;
    $p->status = 'published';
    $p->category_id = $categoryId;

    return $p;
}

/**
 * Constrói N sections em memória, cada uma com M prateleiras.
 *
 * shelf_position é COORDENADA EM CM (0 = topo), não índice: o engine calcula o
 * vão vertical real entre prateleiras (shelfClearances) e rejeita por altura
 * (HeightExceedsShelf) produtos que não cabem. Espaçamento de 50 cm comporta os
 * produtos do fixture (30 cm de altura).
 *
 * @return Collection<int, Section>
 */
function autoE2eSections(int $numModules = 2, int $numShelves = 4, float $width = 100.0): Collection
{
    return collect(range(1, $numModules))->map(function () use ($numShelves, $width): Section {
        $shelves = collect(range(0, $numShelves - 1))->map(function (int $pos): Shelf {
            $s = new Shelf;
            $s->id = (string) Str::ulid();
            $s->shelf_position = $pos * 50;

            return $s;
        });

        $section = new Section;
        $section->id = (string) Str::ulid();
        $section->width = $width;
        $section->cremalheira_width = 0.0;
        $section->setRelation('shelves', $shelves);

        return $section;
    });
}

/**
 * Registra no container um scorer de teste que retorna scores pré-definidos.
 * Nunca faz queries ao banco (resolve o problema das tabelas de venda).
 *
 * @param  array<string, string>  $abcMap  product_id → 'A'|'B'|'C'
 * @param  array<string, float>  $rawQtyMap  product_id → giro bruto
 * @param  array<string, float>  $rawMargemMap  product_id → margem bruta
 */
function autoE2eBindMockScorer(
    array $abcMap = [],
    array $rawQtyMap = [],
    array $rawMargemMap = [],
): void {
    app()->instance(ProductScorerInterface::class, new class($abcMap, $rawQtyMap, $rawMargemMap) implements ProductScorerInterface
    {
        public function __construct(
            private array $abcMap,
            private array $rawQtyMap,
            private array $rawMargemMap,
        ) {}

        public function score(Collection $products, PlacementSettings $settings): Collection
        {
            return $this->scoreOrNeutral($products, $settings);
        }

        public function scoreOrNeutral(Collection $products, PlacementSettings $settings): Collection
        {
            return $products->map(function (Product $p): ScoredProduct {
                $abc = $this->abcMap[$p->id] ?? null;
                $qty = $this->rawQtyMap[$p->id] ?? 0.0;
                $margem = $this->rawMargemMap[$p->id] ?? 0.0;
                $score = match ($abc) {
                    'A' => 0.9,
                    'B' => 0.6,
                    'C' => 0.3,
                    default => 0.5,
                };

                return new ScoredProduct(
                    productId: $p->id,
                    ean: (string) ($p->ean ?? ''),
                    score: $score,
                    product: $p,
                    metadata: [
                        'score_type' => $abc !== null ? 'composite' : 'neutral',
                        'raw_quantity' => $qty,
                        'raw_margem' => $margem,
                    ],
                );
            })->values();
        }
    });
}

/**
 * Constrói um PlanogramInput para o modo automático (sem templateId).
 *
 * @param  Collection<int, Product>  $products
 * @param  Collection<int, Section>  $sections
 * @param  array<string, string>  $abcMap
 */
function autoE2eInput(
    string $gondolaId,
    string $planogramId,
    string $baseCategoryId,
    Collection $products,
    Collection $sections,
    array $abcMap = [],
    ?string $selectedCategoryId = null,
): PlanogramInput {
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
            maxFacings: 5,
            categoryId: $selectedCategoryId ?? $baseCategoryId,
            tenantId: null,
            abcClassMap: $abcMap,
        ),
        planogramCategoryId: $baseCategoryId,
    );
}

/**
 * Executa o pipeline completo de geração automática e retorna o output.
 */
function autoE2eGenerate(PlanogramInput $input): PlanogramOutput
{
    return app(AutoPlanogramService::class)->generate($input);
}

// ── Fixture reutilizável ──────────────────────────────────────────────────────

/**
 * Monta o cenário base usado pela maioria dos testes:
 * Bebidas > Refrigerantes (A), Sucos (neutro), Chás (C)
 * + produto Wide (rejeitado) + produto Direto (descartado silenciosamente).
 *
 * @return array{
 *   gondolaId: string,
 *   planogramId: string,
 *   rootId: string,
 *   refriId: string,
 *   sucosId: string,
 *   chasId: string,
 *   products: Collection,
 *   sections: Collection,
 *   abcMap: array<string,string>,
 *   rawQtyMap: array<string,float>,
 *   rawMargemMap: array<string,float>,
 *   refriA1Id: string,
 *   refriA2Id: string,
 *   refriCId: string,
 *   suco1Id: string,
 *   cha1Id: string,
 *   wideId: string,
 *   diretoId: string,
 * }
 */
function autoE2eFixture(): array
{
    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();

    // Hierarquia de categorias
    $root = autoE2eCategory('Bebidas');
    $refri = autoE2eCategory('Refrigerantes', parentId: $root->id);
    $sucos = autoE2eCategory('Sucos', parentId: $root->id);
    $chas = autoE2eCategory('Chás', parentId: $root->id);

    // Produtos — Refrigerantes (dominante ABC=A)
    $refriA1 = autoE2eProduct($refri->id, width: 8, name: 'Refri A1');
    $refriA2 = autoE2eProduct($refri->id, width: 8, name: 'Refri A2');
    $refriC = autoE2eProduct($refri->id, width: 8, name: 'Refri C');

    // Produto "Wide" em Refrigerantes — mais largo que a prateleira (55 cm > 50 cm)
    // ProductWidthResolver: MAX_PLAUSIBLE_WIDTH=60; width=55 está dentro do threshold.
    // Com min_facings=1, 55 × 1 = 55 > 50 cm (largura da seção) → não cabe → rejeitado.
    $wide = autoE2eProduct($refri->id, width: 55, name: 'Wide (rejeitado)');

    // Produtos — Sucos (neutro, sem venda — para testar scoreOrNeutral)
    $suco1 = autoE2eProduct($sucos->id, width: 8, name: 'Suco sem venda');

    // Produtos — Chás (dominante ABC=C)
    $cha1 = autoE2eProduct($chas->id, width: 8, name: 'Chá C1');
    $cha2 = autoE2eProduct($chas->id, width: 8, name: 'Chá C2');

    // Produto diretamente na categoria selecionada (não numa subcategoria filha)
    $direto = autoE2eProduct($root->id, width: 8, name: 'Direto na raiz');

    $products = collect([$refriA1, $refriA2, $refriC, $wide, $suco1, $cha1, $cha2, $direto]);

    $abcMap = [
        $refriA1->id => 'A',
        $refriA2->id => 'A',
        $refriC->id => 'B',  // neutro para não interferir no teste de zona
        $wide->id => 'A',
        $cha1->id => 'C',
        $cha2->id => 'C',
    ];

    // Giro bruto: Refrigerantes muito alto (→ Destino), Chás e Sucos zero (→ Complementar)
    $rawQtyMap = [
        $refriA1->id => 100.0,
        $refriA2->id => 80.0,
        $refriC->id => 10.0,
        $wide->id => 40.0,
        $suco1->id => 0.0,
        $cha1->id => 0.0,
        $cha2->id => 0.0,
        $direto->id => 0.0,
    ];

    $rawMargemMap = [
        $refriA1->id => 50.0,
        $refriA2->id => 40.0,
        $refriC->id => 5.0,
        $wide->id => 20.0,
        $suco1->id => 0.0,
        $cha1->id => 0.0,
        $cha2->id => 0.0,
        $direto->id => 0.0,
    ];

    // width=50cm para que o produto "Wide" (55cm) não caiba mesmo com 1 frente.
    // ProductWidthResolver::MAX_PLAUSIBLE_WIDTH=60cm → 55 é resolvido como 55 (não cai no fallback).
    $sections = autoE2eSections(numModules: 2, numShelves: 4, width: 50.0);

    return compact(
        'gondolaId', 'planogramId',
        'products', 'sections', 'abcMap', 'rawQtyMap', 'rawMargemMap',
        'sections',
    ) + [
        'rootId' => $root->id,
        'refriId' => $refri->id,
        'sucosId' => $sucos->id,
        'chasId' => $chas->id,
        'refriA1Id' => $refriA1->id,
        'refriA2Id' => $refriA2->id,
        'refriCId' => $refriC->id,
        'suco1Id' => $suco1->id,
        'cha1Id' => $cha1->id,
        'wideId' => $wide->id,
        'diretoId' => $direto->id,
    ];
}

// ── Testes ────────────────────────────────────────────────────────────────────

test('1 — síntese cria template com origin=auto, is_active=false, category_id e source_gondola_id corretos', function (): void {
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);
    autoE2eGenerate($input);

    $template = PlanogramTemplate::first();

    expect($template)->not->toBeNull()
        ->and($template->origin)->toBe('auto')
        ->and($template->is_active)->toBeFalse()
        ->and($template->category_id)->toBe($fx['rootId'])
        ->and($template->source_gondola_id)->toBe($fx['gondolaId']);
});

test('2 — subtemplate usa exatamente os módulos do formulário (2 seções físicas → num_modules=2) e slots cobrem as subcategorias elegíveis', function (): void {
    // 3 subcategorias pequenas: Refrigerantes (58 cm), Sucos (8 cm), Chás (16 cm).
    // Regra "exatamente N": num_modules = nº de seções físicas do formulário (2), não a demanda.
    // Os slots dos 2 módulos (8 no total) cobrem as 3 subcategorias elegíveis.
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);
    autoE2eGenerate($input);

    $subtemplate = PlanogramSubtemplate::first();
    expect($subtemplate)->not->toBeNull()
        ->and($subtemplate->num_modules)->toBe(2);

    $slotCategoryIds = PlanogramTemplateSlot::pluck('category_id')->unique()->values();

    // Cada subcategoria com produtos deve ter pelo menos um slot
    expect($slotCategoryIds)->toContain($fx['refriId'])
        ->and($slotCategoryIds)->toContain($fx['sucosId'])
        ->and($slotCategoryIds)->toContain($fx['chasId']);
});

test('3 — todos os slots sintetizados começam com min_facings=1 (expansão prioriza A→B→C na Phase 2)', function (): void {
    // Para este teste: Refrigerantes é dominante A, Chás é dominante C
    // A prioridade A→B→C é resolvida na expansão de frentes (FacingExpansion::Score → score_abc desc)
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);
    autoE2eGenerate($input);

    $refriSlot = PlanogramTemplateSlot::where('category_id', $fx['refriId'])->first();
    $chasSlot = PlanogramTemplateSlot::where('category_id', $fx['chasId'])->first();

    expect($refriSlot)->not->toBeNull()
        ->and($chasSlot)->not->toBeNull();

    // Todos os slots começam com 1 frente mínima, independente da classe ABC
    expect($refriSlot->getRawOriginal('min_facings'))->toBe(1);
    expect($chasSlot->getRawOriginal('min_facings'))->toBe(1);
});

test('4 — subcategoria de papel destino fica em slots de zona quente', function (): void {
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);
    autoE2eGenerate($input);

    // Refrigerantes tem participação de giro ≈ 1.0 → papel Destino → role_override = 'destino'
    $refriSlots = PlanogramTemplateSlot::where('category_id', $fx['refriId'])->get();

    expect($refriSlots)->not->toBeEmpty();
    expect($refriSlots->first()->getRawOriginal('role_override'))->toBe('destino');

    // Zona quente em 4 prateleiras = shelf_order 2 (Hand) e 3 (Eye)
    // A blocagem vertical coloca Refri (hot role) nos hot slots primeiro
    $hotOrders = [2, 3];
    $refriOrders = $refriSlots->pluck('shelf_order')->unique()->values()->all();
    $atLeastOneHot = count(array_intersect($refriOrders, $hotOrders)) > 0;

    expect($atLeastOneHot)->toBeTrue('Refrigerantes (destino) deve ter ao menos um slot em zona quente');
});

test('5 — produto sem venda é alocado com score neutro (não descartado)', function (): void {
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);
    autoE2eGenerate($input);

    // Suco1 tem raw_quantity=0 (sem venda), mas deve ser alocado via score neutro
    $sucoLayers = Layer::where('product_id', $fx['suco1Id'])->get();

    expect($sucoLayers)->not->toBeEmpty('Produto sem venda deve ser alocado com score neutro');
    // E NÃO deve aparecer como rejeitado
    expect(PlanogramRejectedProduct::where('product_id', $fx['suco1Id'])->count())->toBe(0);
});

test('6 — categoria acima da base do planograma lança ValidationException (HTTP 422)', function (): void {
    $grandParent = autoE2eCategory('Alimentos');
    $root = autoE2eCategory('Bebidas', parentId: $grandParent->id);
    autoE2eCategory('Refrigerantes', parentId: $root->id);

    autoE2eBindMockScorer();

    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();
    $products = collect([autoE2eProduct($grandParent->id)]);
    $sections = autoE2eSections(numModules: 1, numShelves: 2);

    // selectedCategoryId = grandParent (ancestral da base) → deve lançar ValidationException
    $input = autoE2eInput(
        gondolaId: $gondolaId,
        planogramId: $planogramId,
        baseCategoryId: $root->id,       // base do planograma
        products: $products,
        sections: $sections,
        abcMap: [],
        selectedCategoryId: $grandParent->id, // categoria acima da base — inválido
    );

    expect(fn () => autoE2eGenerate($input))->toThrow(ValidationException::class);
});

test('7 — regerar a mesma gôndola não cria segundo template auto (idempotência)', function (): void {
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);

    // Primeira geração
    autoE2eGenerate($input);
    expect(PlanogramTemplate::count())->toBe(1);
    expect(PlanogramSubtemplate::count())->toBe(1);

    $firstTemplateId = PlanogramTemplate::first()->id;
    $firstSlotCount = PlanogramTemplateSlot::count();

    // Segunda geração com mesma gôndola (reggerar do zero)
    autoE2eGenerate($input);

    expect(PlanogramTemplate::count())->toBe(1, 'Não deve criar segundo template');
    expect(PlanogramSubtemplate::count())->toBe(1, 'Não deve criar segundo subtemplate');

    // Template mantém o mesmo ID
    expect(PlanogramTemplate::first()->id)->toBe($firstTemplateId);

    // Slots foram recriados (idempotência — substitui, não acumula)
    expect(PlanogramTemplateSlot::count())->toBe($firstSlotCount);
});

test('8 — produto que não cabe é registrado em planogram_rejected_products com motivo coerente (sem falso-positivo)', function (): void {
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);
    autoE2eGenerate($input);

    // "Wide" (55 cm > 50 cm de largura de prateleira) deve ser rejeitado (não cabe mesmo com 1 frente)
    $rejected = PlanogramRejectedProduct::where('product_id', $fx['wideId'])->first();

    expect($rejected)->not->toBeNull('Produto Wide deve aparecer em planogram_rejected_products')
        ->and($rejected->rejection_reason->value)->toBe('no_horizontal_space');

    // RefriC (rejeitado no slot 1 mas realocado no slot 2) NÃO deve aparecer como rejeitado (sem falso-positivo)
    expect(PlanogramRejectedProduct::where('product_id', $fx['refriCId'])->count())->toBe(0);
});

test('9 — produto diretamente na categoria selecionada é descartado silenciosamente (sem slot e sem rejeição)', function (): void {
    /**
     * Comportamento documentado: o AutoTemplateSynthesisOrchestrator::groupBySubcategory()
     * só mapeia produtos cujo category_id pertence a uma SUBCATEGORIA FILHA da categoria
     * selecionada. Produtos pendurados diretamente na categoria selecionada não encontram
     * bucket de agrupamento e são descartados silenciosamente.
     *
     * Em seguida, no placement, findCandidates() só considera produtos cujo category_id
     * está entre os descendentes do category_id do slot (sempre uma subcategoria filha).
     * Um produto com category_id = categoria-base nunca entra nos descendentes de uma filha.
     *
     * Resultado esperado: nem alocado nem rejeitado — simplesmente fora do escopo.
     */
    $fx = autoE2eFixture();
    autoE2eBindMockScorer($fx['abcMap'], $fx['rawQtyMap'], $fx['rawMargemMap']);

    $input = autoE2eInput($fx['gondolaId'], $fx['planogramId'], $fx['rootId'], $fx['products'], $fx['sections'], $fx['abcMap']);
    autoE2eGenerate($input);

    // Produto "Direto" (category_id = rootId = "Bebidas") não deve ter sido alocado
    expect(Layer::where('product_id', $fx['diretoId'])->count())->toBe(0);

    // Nem deve aparecer em planogram_rejected_products
    expect(PlanogramRejectedProduct::where('product_id', $fx['diretoId'])->count())->toBe(0);
});

test('10 — categoria intermediária (Flocão) é expandida para filhos; De Milho e De Arroz recebem slots separados', function (): void {
    /**
     * Cenário: Cereais → Flocão → [De Milho, De Arroz]
     *
     * No modo automático, "Flocão" é um nó intermediário sem produtos diretos.
     * O sistema deve percorrer a árvore e criar um slot para cada filho de "Flocão",
     * em vez de agrupar "De Milho" e "De Arroz" na mesma prateleira.
     *
     * Esperado:
     * - Template slots cobrem "De Milho" e "De Arroz" (NÃO "Flocão").
     * - Produtos de "De Milho" e "De Arroz" são alocados em prateleiras distintas.
     */
    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();

    $cereais = autoE2eCategory('Cereais');
    $floacao = autoE2eCategory('Flocão', parentId: $cereais->id);
    $deMilho = autoE2eCategory('De Milho', parentId: $floacao->id);
    $deArroz = autoE2eCategory('De Arroz', parentId: $floacao->id);

    $milhoP1 = autoE2eProduct($deMilho->id, width: 8, name: 'Flocão Milho 1');
    $milhoP2 = autoE2eProduct($deMilho->id, width: 8, name: 'Flocão Milho 2');
    $arrozP1 = autoE2eProduct($deArroz->id, width: 8, name: 'Flocão Arroz 1');
    $arrozP2 = autoE2eProduct($deArroz->id, width: 8, name: 'Flocão Arroz 2');

    $products = collect([$milhoP1, $milhoP2, $arrozP1, $arrozP2]);

    $abcMap = [
        $milhoP1->id => 'A',
        $milhoP2->id => 'B',
        $arrozP1->id => 'A',
        $arrozP2->id => 'C',
    ];

    autoE2eBindMockScorer($abcMap, [], []);

    // 1 módulo × 4 prateleiras × 100 cm → espaço farto para todos os produtos
    $sections = autoE2eSections(numModules: 1, numShelves: 4, width: 100.0);

    $input = autoE2eInput($gondolaId, $planogramId, $cereais->id, $products, $sections, $abcMap);
    autoE2eGenerate($input);

    // Os slots sintetizados devem cobrir as folhas "De Milho" e "De Arroz"
    $slotCategoryIds = PlanogramTemplateSlot::pluck('category_id')->unique()->values()->all();

    expect($slotCategoryIds)->toContain($deMilho->id);
    expect($slotCategoryIds)->toContain($deArroz->id);

    // "Flocão" NÃO deve ser usado como slot (foi expandido para os filhos)
    expect($slotCategoryIds)->not->toContain($floacao->id);

    // Todos os produtos devem ter sido alocados
    expect(Layer::where('product_id', $milhoP1->id)->count())->toBeGreaterThan(0);
    expect(Layer::where('product_id', $milhoP2->id)->count())->toBeGreaterThan(0);
    expect(Layer::where('product_id', $arrozP1->id)->count())->toBeGreaterThan(0);
    expect(Layer::where('product_id', $arrozP2->id)->count())->toBeGreaterThan(0);
});

test('11 — categoria folha selecionada diretamente não é expandida (comportamento preservado)', function (): void {
    /**
     * Garante que a expansão não afeta o caso de categoria-folha:
     * quando a categoria selecionada não tem filhos, comportamento idêntico ao anterior.
     */
    $gondolaId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();

    $bebidas = autoE2eCategory('Bebidas');
    $refri = autoE2eCategory('Refrigerantes', parentId: $bebidas->id);  // sem filhos

    $p1 = autoE2eProduct($refri->id, width: 8, name: 'Refri 1');
    $p2 = autoE2eProduct($refri->id, width: 8, name: 'Refri 2');

    $abcMap = [$p1->id => 'A', $p2->id => 'B'];

    autoE2eBindMockScorer($abcMap);

    $sections = autoE2eSections(numModules: 1, numShelves: 4, width: 100.0);

    // selectedCategoryId = $refri (categoria folha com produtos diretos)
    $input = autoE2eInput($gondolaId, $planogramId, $refri->id, collect([$p1, $p2]), $sections, $abcMap);
    autoE2eGenerate($input);

    // Categoria folha selecionada como base: sem filhos → SlotPlanBuilder usa buildLeafPlan
    // com a própria categoria em todos os slots
    $slotCategoryIds = PlanogramTemplateSlot::pluck('category_id')->unique()->values()->all();
    expect($slotCategoryIds)->toContain($refri->id);

    // Produtos devem estar alocados
    expect(Layer::where('product_id', $p1->id)->count())->toBeGreaterThan(0);
    expect(Layer::where('product_id', $p2->id)->count())->toBeGreaterThan(0);
});
