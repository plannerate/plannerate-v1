<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dry-run: o pipeline calcula o layout completo sem tocar no banco.
 *
 * É a fundação da reotimização contínua — a proposta que o usuário revisa antes de aprovar
 * é produzida por um dry-run. Se ele vazar escrita, uma "simulação" agendada corromperia
 * gôndolas em produção sem ninguém pedir.
 *
 * O dry-run só existe em modo template (o modo automático sintetiza o template no banco),
 * então todos os cenários aqui montam template + subtemplate + slots reais.
 */

require_once __DIR__.'/helpers.php';

beforeEach(function (): void {
    // Ordem importa: makeCurrent() reconecta a conexão `tenant` ao SEU :memory:. Sem isso,
    // `tenant` aponta para o mesmo banco da conexão padrão e o dropAllTables abaixo apagaria
    // o schema da aplicação inteira, derrubando os testes seguintes da suíte.
    fakeReoptimizationTenant();
    buildDryRunSchema();
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

// ── Testes ───────────────────────────────────────────────────────────────────

test('dry-run calcula o layout sem persistir nada', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario();
    bindDeterministicScorer($products);

    $dryInput = new PlanogramInput(
        planogramId: $input->planogramId,
        gondolaId: $input->gondolaId,
        tenantId: $input->tenantId,
        products: $input->products,
        sections: $input->sections,
        settings: $input->settings,
        planogramCategoryId: $input->planogramCategoryId,
        dryRun: true,
    );

    $output = app(AutoPlanogramService::class)->generate($dryInput);

    // O layout foi realmente calculado — senão o teste passaria com um pipeline quebrado.
    expect($output->placedSegments)->not->toBeEmpty();

    // E nada foi gravado.
    expect(Segment::withTrashed()->count())->toBe(0)
        ->and(Layer::withTrashed()->count())->toBe(0)
        ->and(DB::connection('tenant')->table('planogram_rejected_products')->count())->toBe(0);

    // Nem o bookkeeping: o subtemplate usado não é registrado no planograma.
    $planogram = DB::connection('tenant')->table('planograms')->where('id', $input->planogramId)->first();
    expect($planogram->subtemplate_id)->toBeNull();
});

test('geração normal persiste — contraprova do dry-run', function (): void {
    ['input' => $input, 'products' => $products, 'subtemplateId' => $subtemplateId] = makeTemplateScenario();
    bindDeterministicScorer($products);

    $output = app(AutoPlanogramService::class)->generate($input);

    expect($output->placedSegments)->not->toBeEmpty()
        ->and(Segment::count())->toBe($output->placedSegments->count())
        ->and(Layer::count())->toBeGreaterThan(0);

    $planogram = DB::connection('tenant')->table('planograms')->where('id', $input->planogramId)->first();
    expect($planogram->subtemplate_id)->toBe($subtemplateId);
});

test('dry-run em modo automático é rejeitado', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario();
    bindDeterministicScorer($products);

    // Sem templateId → modo automático, que sintetiza template no banco: dry-run é inseguro.
    $automaticSettings = new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: 1,
        maxFacings: 4,
        groupBySubcategory: false,
        includeProductsWithoutSales: true,
        categoryId: $input->planogramCategoryId,
    );

    $badInput = new PlanogramInput(
        planogramId: $input->planogramId,
        gondolaId: $input->gondolaId,
        tenantId: $input->tenantId,
        products: $input->products,
        sections: $input->sections,
        settings: $automaticSettings,
        planogramCategoryId: $input->planogramCategoryId,
        dryRun: true,
    );

    expect(fn () => app(AutoPlanogramService::class)->generate($badInput))
        ->toThrow(LogicException::class);
});

/**
 * Regeração não pode deixar Layers órfãs (deleted_at NULL apontando para Segment deletado).
 * O writer soft-deletava os Segments sem cascatear as Layers — lixo acumulado a cada geração.
 * A reotimização agendada multiplicaria isso por N gôndolas × N ciclos.
 */
test('regeração não deixa layers órfãs', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario();
    bindDeterministicScorer($products);

    app(AutoPlanogramService::class)->generate($input);
    app(AutoPlanogramService::class)->generate($input);

    $liveSegmentIds = Segment::pluck('id');
    $orphanLayers = Layer::whereNotIn('segment_id', $liveSegmentIds)->count();

    expect($orphanLayers)->toBe(0)
        ->and(Layer::count())->toBeGreaterThan(0);
});

/**
 * CANÁRIO: se o placement não for determinístico, o diff da reotimização vira ruído
 * ("produto X mudou de prateleira" sem nada ter mudado) e a feature inteira perde
 * credibilidade. Este teste tem que passar antes de qualquer coisa depender do diff.
 */
test('dois dry-runs com a mesma entrada produzem o mesmo layout', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario(numModules: 3, shelvesPerModule: 4, numProducts: 20);
    bindDeterministicScorer($products);

    $dryInput = new PlanogramInput(
        planogramId: $input->planogramId,
        gondolaId: $input->gondolaId,
        tenantId: $input->tenantId,
        products: $input->products,
        sections: $input->sections,
        settings: $input->settings,
        planogramCategoryId: $input->planogramCategoryId,
        dryRun: true,
    );

    $first = app(AutoPlanogramService::class)->generate($dryInput);
    $second = app(AutoPlanogramService::class)->generate($dryInput);

    expect(layoutFingerprint($second->placedSegments))
        ->toEqual(layoutFingerprint($first->placedSegments));
});
