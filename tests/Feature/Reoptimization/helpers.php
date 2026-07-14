<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\ProductScorerInterface;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fixtures compartilhadas dos testes de reotimização.
 *
 * Vivem fora dos arquivos de teste porque o Pest carrega o escopo global de cada arquivo
 * isoladamente: uma função declarada em DryRunTest.php não existe ao rodar apenas
 * ProposalApplicationTest.php.
 */

// ── Schema ───────────────────────────────────────────────────────────────────

/** Tenant corrente reaproveitando o database da conexão de teste (padrão de AutoPlanogramQueueTest). */
function fakeReoptimizationTenant(): Tenant
{
    $defaultConnection = (string) config('database.default');

    $tenant = new Tenant;
    $tenant->id = '01jtenantreopt000000000000';
    $tenant->database = (string) config("database.connections.{$defaultConnection}.database", 'testing');

    Tenant::forgetCurrent();
    $tenant->makeCurrent();

    return $tenant;
}

function buildDryRunSchema(): void
{
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

    Schema::connection('tenant')->create('planograms', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('subtemplate_id', 26)->nullable();
        $table->string('name')->nullable();
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
        $table->boolean('is_active')->default(true);
        $table->string('origin')->nullable();
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
        $table->string('price_order')->nullable();
        $table->string('size_order')->nullable();
        $table->string('brand_exposure')->nullable();
        $table->string('flavor_exposure')->nullable();
        $table->unsignedTinyInteger('max_share_per_sku')->nullable();
        $table->unsignedTinyInteger('max_share_per_brand')->nullable();
        $table->unsignedTinyInteger('max_share_per_subcategory')->nullable();
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->unsignedSmallInteger('priority')->default(1);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26)->nullable()->index();
        $table->integer('width')->default(100);
        $table->integer('height')->default(200);
        $table->integer('cremalheira_width')->default(0);
        $table->integer('ordering')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('shelves', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('section_id', 26)->nullable()->index();
        $table->integer('ordering')->default(0);
        $table->integer('shelf_position')->default(0);
        $table->float('shelf_width')->default(100.0);
        $table->float('shelf_height')->default(4.0);
        $table->float('shelf_depth')->default(40.0);
        $table->string('product_type')->default('normal');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('segments', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('shelf_id', 26)->nullable()->index();
        $table->integer('quantity')->default(0);
        $table->integer('ordering')->default(0);
        $table->integer('position')->default(0);
        $table->integer('width')->default(0);
        $table->integer('distributed_width')->default(0);
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('layers', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('segment_id', 26)->nullable()->index();
        $table->char('product_id', 26)->nullable();
        $table->string('ean')->nullable();
        $table->integer('quantity')->default(1);
        $table->integer('height')->default(1);
        $table->string('status')->default('published');
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

    Schema::connection('tenant')->create('adjacency_rules', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('source_category_id', 26)->nullable();
        $table->char('target_category_id', 26)->nullable();
        $table->string('rule_type')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_product_rules', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('product_id', 26)->nullable();
        $table->string('brand')->nullable();
        $table->char('subcategory_id', 26)->nullable();
        $table->string('rule_type')->nullable();
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

    // Toda geração passa pelo GenerationQueueDispatcher, que invalida propostas pendentes.
    buildReoptimizationProposalsTable();
}

// ── Fixtures ─────────────────────────────────────────────────────────────────

/**
 * Monta um cenário completo em modo template: categoria, template com N módulos × M
 * prateleiras de slots, produtos vinculados à categoria, e as sections/shelves físicas.
 *
 * @return array{input: PlanogramInput, products: Collection<int, Product>, templateId: string}
 */
function makeTemplateScenario(int $numModules = 2, int $shelvesPerModule = 3, int $numProducts = 8): array
{
    $categoryId = (string) Str::ulid();
    DB::connection('tenant')->table('categories')->insert([
        'id' => $categoryId,
        'name' => 'Categoria de teste',
        'slug' => 'categoria-teste-'.substr($categoryId, -6),
        'category_id' => null,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planogramId = (string) Str::ulid();
    DB::connection('tenant')->table('planograms')->insert([
        'id' => $planogramId,
        'name' => 'Planograma de teste',
        'subtemplate_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $templateId = (string) Str::ulid();
    DB::connection('tenant')->table('planogram_templates')->insert([
        'id' => $templateId,
        'code' => 'TPL-TEST',
        'name' => 'Template de teste',
        'department' => 'MERCEARIA',
        'category_id' => $categoryId,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $subtemplateId = (string) Str::ulid();
    DB::connection('tenant')->table('planogram_subtemplates')->insert([
        'id' => $subtemplateId,
        'template_id' => $templateId,
        'code' => 'SUB-TEST',
        'num_modules' => $numModules,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Um slot por (módulo × prateleira), todos na mesma categoria.
    $slots = [];
    for ($module = 1; $module <= $numModules; $module++) {
        for ($shelf = 1; $shelf <= $shelvesPerModule; $shelf++) {
            $slots[] = [
                'id' => (string) Str::ulid(),
                'subtemplate_id' => $subtemplateId,
                'category_id' => $categoryId,
                'module_number' => $module,
                'shelf_order' => $shelf,
                'min_facings' => 1,
                'max_facings' => 4,
                'facing_expansion' => 'score',
                'space_fallback' => 'reduce_c',
                'use_target_stock' => false,
                'ordering' => $shelf,
                'priority' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }
    DB::connection('tenant')->table('planogram_template_slots')->insert($slots);

    $products = collect(range(0, $numProducts - 1))->map(function (int $i) use ($categoryId): Product {
        $product = new Product;
        $product->id = (string) Str::ulid();
        $product->tenant_id = 'tenant-test';
        $product->category_id = $categoryId;
        $product->name = "Produto {$i}";
        $product->ean = str_pad((string) ($i + 1000), 13, '0', STR_PAD_LEFT);
        $product->width = 10;
        $product->height = 25;
        $product->depth = 30;
        $product->status = 'published';

        return $product;
    });

    // Sections/shelves físicas persistidas: a validação lê sections direto do banco, e os
    // Segments gravados referenciam shelf_id (o writer deleta por shelf_id).
    $gondolaId = (string) Str::ulid();
    $sections = collect(range(0, $numModules - 1))->map(function (int $s) use ($gondolaId, $shelvesPerModule): Section {
        $section = new Section;
        $section->forceFill([
            'id' => (string) Str::ulid(),
            'gondola_id' => $gondolaId,
            'width' => 100,
            'height' => 200,
            'cremalheira_width' => 0,
            'ordering' => $s,
        ])->save();

        $shelves = collect(range(0, $shelvesPerModule - 1))->map(function (int $i) use ($section): Shelf {
            $shelf = new Shelf;
            $shelf->forceFill([
                'id' => (string) Str::ulid(),
                'section_id' => $section->id,
                'shelf_width' => 100,
                'shelf_height' => 4,
                'shelf_depth' => 40,
                'ordering' => $i,
                'shelf_position' => 30 + ($i * 34),
            ])->save();

            return $shelf;
        });

        $section->setRelation('shelves', $shelves);

        return $section;
    });

    $settings = (new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: 1,
        maxFacings: 4,
        groupBySubcategory: false,
        includeProductsWithoutSales: true,
        categoryId: null,
    ))->withTemplate(
        templateId: $templateId,
        numModules: $numModules,
        planogramId: $planogramId,
        products: $products,
    );

    $input = new PlanogramInput(
        planogramId: $planogramId,
        gondolaId: $gondolaId,
        tenantId: 'tenant-test',
        products: $products,
        sections: $sections,
        settings: $settings,
        planogramCategoryId: $categoryId,
    );

    return [
        'input' => $input,
        'products' => $products,
        'templateId' => $templateId,
        'subtemplateId' => $subtemplateId,
    ];
}

/** Scorer determinístico: score decrescente na ordem dos produtos. */
function bindDeterministicScorer(Collection $products): void
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

    app()->bind(ProductScorerInterface::class, fn () => new class($scored) implements ProductScorerInterface
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
    });
}

/** Assinatura estável de um layout, para comparar duas execuções. */
function layoutFingerprint(Collection $placedSegments): array
{
    return $placedSegments
        ->map(fn (PlacedSegment $s) => [
            'shelf_id' => $s->shelfId,
            'ordering' => $s->ordering,
            'position' => $s->position,
            'width' => $s->width,
            'layers' => $s->layers
                ->map(fn ($l) => ['product_id' => $l->productId, 'quantity' => $l->quantity, 'height' => $l->height])
                ->all(),
        ])
        ->sortBy(fn (array $s) => $s['shelf_id'].'-'.str_pad((string) $s['ordering'], 4, '0', STR_PAD_LEFT))
        ->values()
        ->all();
}

/** gondolas não está no schema do DryRunTest (lá o motor recebe as sections direto). */
function buildProposalSchema(): void
{
    buildDryRunSchema();

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->string('name')->nullable();
        $table->string('template_id')->nullable();
        $table->string('generation_mode')->nullable();
        $table->boolean('reoptimization_enabled')->default(false);
        $table->string('reoptimization_frequency', 20)->nullable();
        $table->timestamp('reoptimization_last_run_at')->nullable();
        $table->timestamp('reoptimization_next_run_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_generation_runs', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26);
        $table->char('gondola_id', 26);
        $table->char('user_id', 26)->nullable();
        $table->string('status')->default('queued');
        $table->string('mode');
        $table->string('kind')->default('apply');
        $table->string('trigger')->default('manual');
        $table->json('config_snapshot')->nullable();
        $table->char('template_id', 26)->nullable();
        $table->char('synth_template_id', 26)->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->integer('duration_ms')->nullable();
        $table->float('occupancy_avg')->nullable();
        $table->float('occupancy_min')->nullable();
        $table->float('occupancy_max')->nullable();
        $table->integer('iterations_run')->nullable();
        $table->boolean('converged')->nullable();
        $table->json('capacity_report')->nullable();
        $table->json('validation_report')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}
