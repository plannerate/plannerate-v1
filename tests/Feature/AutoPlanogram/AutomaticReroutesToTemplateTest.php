<?php

use App\Models\Category;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Synthesis\AutoTemplateSynthesisOrchestrator;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('categories', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('category_id', 26)->nullable()->index(); // parent FK
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('status')->default('active');
        $table->integer('hierarchy_position')->nullable();
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
});

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Persiste uma categoria na tabela tenant e retorna o model.
 */
function rouCategory(string $name, ?string $parentId = null): Category
{
    $id = (string) Str::ulid();
    DB::connection('tenant')->table('categories')->insert([
        'id' => $id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.$id,
        'category_id' => $parentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Category::find($id);
}

/**
 * Cria ScoredProduct sintético vinculado a uma category_id.
 *
 * @param  'A'|'B'|'C'  $abc
 */
function rouScored(string $categoryId, string $abc, float $qty = 1.0, float $margem = 1.0): ScoredProduct
{
    $p = new Product;
    $p->id = (string) Str::ulid();
    $p->name = "Produto {$abc}";
    $p->category_id = $categoryId;

    return new ScoredProduct(
        productId: $p->id,
        ean: '0000000000000',
        score: match ($abc) {
            'A' => 90.0,
            'B' => 60.0,
            default => 30.0,
        },
        product: $p,
        metadata: [
            'score_type' => 'composite',
            'raw_quantity' => $qty,
            'raw_margem' => $margem,
        ],
    );
}

function rouInput(string $gondolaId, ?string $categoryId = null, array $abcMap = []): PlanogramInput
{
    return new PlanogramInput(
        planogramId: (string) Str::ulid(),
        gondolaId: $gondolaId,
        tenantId: 'test',
        products: collect(),
        sections: collect(),
        settings: new PlacementSettings(
            strategy: 'abc',
            useExistingAnalysis: false,
            startDate: null,
            endDate: null,
            minFacings: 1,
            maxFacings: 5,
            categoryId: $categoryId,
            abcClassMap: $abcMap,
        ),
    );
}

function rouOrchestrator(): AutoTemplateSynthesisOrchestrator
{
    return app(AutoTemplateSynthesisOrchestrator::class);
}

// ── Testes ────────────────────────────────────────────────────────────────────

test('categoria selecionada acima da base lança ValidationException', function (): void {
    $root = rouCategory('Raiz');
    $child = rouCategory('Filho', parentId: $root->id);

    $gondolaId = (string) Str::ulid();
    // Base do planograma é "Filho"; seleciona "Raiz" (ancestral) — inválido
    $input = rouInput(gondolaId: $gondolaId, categoryId: $root->id);

    expect(fn () => rouOrchestrator()->orchestrate($input, collect(), $child->id))
        ->toThrow(ValidationException::class);
});

test('categoria igual à base do planograma é válida e sintetiza template', function (): void {
    $base = rouCategory('Bebidas');
    $sub = rouCategory('Águas', parentId: $base->id);

    $gondolaId = (string) Str::ulid();
    $scored = collect([
        rouScored($sub->id, 'B'),
    ]);

    $input = rouInput(gondolaId: $gondolaId, categoryId: $base->id);

    $subtemplate = rouOrchestrator()->orchestrate($input, $scored, $base->id);

    expect(PlanogramTemplate::count())->toBe(1)
        ->and(PlanogramTemplate::first()->origin)->toBe('auto')
        ->and(PlanogramTemplate::first()->is_active)->toBeFalse()
        ->and($subtemplate)->toBeInstanceOf(PlanogramSubtemplate::class);
});

test('abcClassMap injetado não altera min_facings (todos começam com 1; expansão prioriza A→B→C)', function (): void {
    $base = rouCategory('Limpeza');
    $catA = rouCategory('Sabão', parentId: $base->id);
    $catC = rouCategory('Flanela', parentId: $base->id);

    $gondolaId = (string) Str::ulid();

    $prodA1 = (string) Str::ulid();
    $prodC1 = (string) Str::ulid();

    $abcMap = [$prodA1 => 'A', $prodC1 => 'C'];

    // Produz scored com produtos mapeados às categorias corretas
    $pA = new Product;
    $pA->id = $prodA1;
    $pA->name = 'Produto A';
    $pA->category_id = $catA->id;

    $pC = new Product;
    $pC->id = $prodC1;
    $pC->name = 'Produto C';
    $pC->category_id = $catC->id;

    $scored = collect([
        new ScoredProduct($prodA1, '0000000000001', 90.0, $pA, ['score_type' => 'composite', 'raw_quantity' => 0.5, 'raw_margem' => 0.5]),
        new ScoredProduct($prodC1, '0000000000002', 30.0, $pC, ['score_type' => 'composite', 'raw_quantity' => 0.5, 'raw_margem' => 0.5]),
    ]);

    $input = rouInput(gondolaId: $gondolaId, categoryId: $base->id, abcMap: $abcMap);

    rouOrchestrator()->orchestrate($input, $scored, $base->id);

    $slots = PlanogramTemplateSlot::get()->groupBy('category_id');

    $facingsA = $slots->get($catA->id)?->first()?->getRawOriginal('min_facings');
    $facingsC = $slots->get($catC->id)?->first()?->getRawOriginal('min_facings');

    // Todos os slots começam com 1 frente mínima, independente da classe ABC
    expect($facingsA)->not->toBeNull()
        ->and($facingsC)->not->toBeNull()
        ->and($facingsA)->toBe(1)
        ->and($facingsC)->toBe(1);
});

test('participações normalizadas fazem subcategorias receberem papéis distintos', function (): void {
    $base = rouCategory('Alimentos');
    $catAlta = rouCategory('Premium', parentId: $base->id);   // alto giro + margem → Destino
    $catBaixa = rouCategory('Genérico', parentId: $base->id); // baixo giro + margem → Complementar

    $gondolaId = (string) Str::ulid();

    // "Premium": qty=10, margem=10 → share 10/(10+0.1) ≈ 0.99 nos dois eixos
    // "Genérico": qty=0.1, margem=0.1 → share ≈ 0.01

    $pHigh = new Product;
    $pHigh->id = (string) Str::ulid();
    $pHigh->category_id = $catAlta->id;

    $pLow = new Product;
    $pLow->id = (string) Str::ulid();
    $pLow->category_id = $catBaixa->id;

    $scored = collect([
        new ScoredProduct($pHigh->id, '0000000000003', 90.0, $pHigh, ['score_type' => 'composite', 'raw_quantity' => 10.0, 'raw_margem' => 10.0]),
        new ScoredProduct($pLow->id, '0000000000004', 10.0, $pLow, ['score_type' => 'composite', 'raw_quantity' => 0.1, 'raw_margem' => 0.1]),
    ]);

    $input = rouInput(gondolaId: $gondolaId, categoryId: $base->id);

    rouOrchestrator()->orchestrate($input, $scored, $base->id);

    $slots = PlanogramTemplateSlot::get()->groupBy('category_id');

    $roleAlta = $slots->get($catAlta->id)?->first()?->getRawOriginal('role_override');
    $roleBaixa = $slots->get($catBaixa->id)?->first()?->getRawOriginal('role_override');

    expect($roleAlta)->not->toBeNull()
        ->and($roleBaixa)->not->toBeNull()
        ->and($roleAlta)->not->toBe($roleBaixa); // papéis distintos — normalização funcionou
});

test('CategoryAbcSummary::fromScoredProducts usa abcClassMap em vez de metadata tardio', function (): void {
    $prodId = (string) Str::ulid();

    $p = new Product;
    $p->id = $prodId;
    $p->category_id = 'cat-x';

    $sp = new ScoredProduct(
        productId: $prodId,
        ean: '0000000000005',
        score: 80.0,
        product: $p,
        metadata: ['score_type' => 'composite', 'raw_quantity' => 1.0, 'raw_margem' => 1.0],
        // Sem abc_class no metadata (comportamento real — só é setado tarde no engine)
    );

    // Com mapa: produto é A
    $withMap = CategoryAbcSummary::fromScoredProducts('cat-x', collect([$sp]), [$prodId => 'A']);
    expect($withMap->dominantAbcClass)->toBe('A');

    // Sem mapa: dominantAbcClass deve ser null (metadata não tem abc_class)
    $withoutMap = CategoryAbcSummary::fromScoredProducts('cat-x', collect([$sp]), []);
    expect($withoutMap->dominantAbcClass)->toBeNull();
});

test('CategoryAbcSummary::withParticipation retorna cópia com valores normalizados', function (): void {
    $summary = new CategoryAbcSummary('cat-y', 50.0, 30.0, 10, 'B');
    $norm = $summary->withParticipation(0.4, 0.3);

    expect($norm->totalQuantity)->toBe(0.4)
        ->and($norm->totalMargem)->toBe(0.3)
        ->and($norm->categoryId)->toBe('cat-y')
        ->and($norm->dominantAbcClass)->toBe('B')
        ->and($norm->skuCount)->toBe(10);

    // Original inalterado
    expect($summary->totalQuantity)->toBe(50.0);
});
