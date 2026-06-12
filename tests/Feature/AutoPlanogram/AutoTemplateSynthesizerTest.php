<?php

use App\Models\Category;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\SlotPlanEntry;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Synthesis\AutoTemplateSynthesizer;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('planogram_templates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('code');
        $table->string('name');
        $table->string('department');
        $table->char('category_id', 26)->nullable();
        $table->text('description')->nullable();
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
});

/** Cria um objeto Category sem persistir (basta name para a síntese). */
function synthCategory(string $name = 'Bebidas'): Category
{
    $cat = new Category;
    $cat->id = (string) Str::ulid();
    $cat->name = $name;

    return $cat;
}

/**
 * Cria um SlotPlanEntry mínimo.
 *
 * @param  int  $module  Número do módulo (1..N)
 * @param  int  $shelf  Ordem da prateleira (1=chão)
 */
function synthEntry(string $categoryId, int $module, int $shelf, int $minFacings = 2): SlotPlanEntry
{
    return new SlotPlanEntry(
        categoryId: $categoryId,
        moduleNumber: $module,
        shelfOrder: $shelf,
        minFacings: $minFacings,
        visualCriteria: [['key' => 'score_abc', 'direction' => 'desc']],
        zone: 'hot',
        roleOverride: null,
    );
}

test('síntese cria template com origin=auto, is_active=false e category_id correto', function (): void {
    $gondolaId = (string) Str::ulid();
    $baseCategoryId = (string) Str::ulid();
    $selectedCat = synthCategory('Bebidas');

    $slotPlan = [
        synthEntry((string) Str::ulid(), module: 1, shelf: 1, minFacings: 3),
        synthEntry((string) Str::ulid(), module: 1, shelf: 2, minFacings: 2),
    ];

    (new AutoTemplateSynthesizer)->synthesize(
        planogramBaseCategoryId: $baseCategoryId,
        selectedCategory: $selectedCat,
        slotPlan: $slotPlan,
        numModules: 1,
        gondolaId: $gondolaId,
    );

    $template = PlanogramTemplate::first();

    expect($template)->not->toBeNull()
        ->and($template->origin)->toBe('auto')
        ->and($template->is_active)->toBeFalse()
        ->and($template->category_id)->toBe($baseCategoryId)
        ->and($template->source_gondola_id)->toBe($gondolaId)
        ->and($template->name)->toBe('Bebidas (auto)')
        ->and($template->code)->toStartWith('AUTO-');
});

test('síntese cria subtemplate com num_modules correto', function (): void {
    $gondolaId = (string) Str::ulid();
    $baseCategoryId = (string) Str::ulid();

    $slotPlan = [
        synthEntry((string) Str::ulid(), module: 1, shelf: 1),
        synthEntry((string) Str::ulid(), module: 2, shelf: 1),
    ];

    (new AutoTemplateSynthesizer)->synthesize(
        planogramBaseCategoryId: $baseCategoryId,
        selectedCategory: synthCategory(),
        slotPlan: $slotPlan,
        numModules: 3,
        gondolaId: $gondolaId,
    );

    $template = PlanogramTemplate::first();
    $subtemplate = PlanogramSubtemplate::where('template_id', $template->id)->first();

    expect($subtemplate)->not->toBeNull()
        ->and($subtemplate->num_modules)->toBe(3)
        ->and($subtemplate->is_active)->toBeTrue()
        ->and($subtemplate->code)->toBe($template->code.'-3M');
});

test('síntese cria slots que correspondem ao plano (contagem e category_id)', function (): void {
    $gondolaId = (string) Str::ulid();
    $catA = (string) Str::ulid();
    $catB = (string) Str::ulid();
    $catC = (string) Str::ulid();

    $slotPlan = [
        synthEntry($catA, module: 1, shelf: 1, minFacings: 3),
        synthEntry($catB, module: 1, shelf: 2, minFacings: 2),
        synthEntry($catC, module: 2, shelf: 1, minFacings: 1),
    ];

    (new AutoTemplateSynthesizer)->synthesize(
        planogramBaseCategoryId: (string) Str::ulid(),
        selectedCategory: synthCategory(),
        slotPlan: $slotPlan,
        numModules: 2,
        gondolaId: $gondolaId,
    );

    $subtemplate = PlanogramSubtemplate::first();
    $slots = PlanogramTemplateSlot::where('subtemplate_id', $subtemplate->id)->get();

    expect($slots)->toHaveCount(3);

    $byModuleShelf = $slots->keyBy(fn ($s) => "{$s->module_number}:{$s->shelf_order}");
    expect($byModuleShelf->has('1:1'))->toBeTrue()
        ->and($byModuleShelf->has('1:2'))->toBeTrue()
        ->and($byModuleShelf->has('2:1'))->toBeTrue();

    expect($byModuleShelf['1:1']->category_id)->toBe($catA)
        ->and($byModuleShelf['1:1']->getRawOriginal('min_facings'))->toBe(3)
        ->and($byModuleShelf['1:2']->category_id)->toBe($catB)
        ->and($byModuleShelf['2:1']->category_id)->toBe($catC);
});

test('regenerar a mesma gôndola não duplica template (idempotente por source_gondola_id)', function (): void {
    $gondolaId = (string) Str::ulid();
    $baseCategoryId = (string) Str::ulid();
    $catA = (string) Str::ulid();
    $catB = (string) Str::ulid();

    $synthesizer = new AutoTemplateSynthesizer;

    // 1ª geração: 1 slot
    $synthesizer->synthesize(
        planogramBaseCategoryId: $baseCategoryId,
        selectedCategory: synthCategory('Bebidas'),
        slotPlan: [synthEntry($catA, module: 1, shelf: 1)],
        numModules: 2,
        gondolaId: $gondolaId,
    );

    expect(PlanogramTemplate::count())->toBe(1);
    expect(PlanogramTemplateSlot::count())->toBe(1);

    // 2ª geração: plano diferente (2 slots) para a mesma gôndola
    $synthesizer->synthesize(
        planogramBaseCategoryId: $baseCategoryId,
        selectedCategory: synthCategory('Bebidas'),
        slotPlan: [
            synthEntry($catA, module: 1, shelf: 1),
            synthEntry($catB, module: 1, shelf: 2),
        ],
        numModules: 2,
        gondolaId: $gondolaId,
    );

    // Não deve ter duplicado o template nem o subtemplate
    expect(PlanogramTemplate::count())->toBe(1);
    expect(PlanogramSubtemplate::count())->toBe(1);

    // Slots devem refletir o novo plano (2 slots, não 1+2=3)
    expect(PlanogramTemplateSlot::count())->toBe(2);
});
