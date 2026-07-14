<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Locking\LockedShelfProducts;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/helpers.php';

/**
 * Prateleira travada: a geração preserva, e o produto que está nela não reaparece em outro lugar.
 *
 * São as duas metades da mesma promessa. Se o writer apagasse a prateleira, "travar" deixaria a
 * prateleira VAZIA — o oposto do que o nome diz. Se o produto continuasse no pool, ele seria
 * posicionado de novo em outra prateleira e o mesmo SKU apareceria duas vezes na gôndola.
 */

/** IDs dos segments de uma prateleira. */
function segmentIdsOfShelf(string $shelfId): array
{
    return Segment::where('shelf_id', $shelfId)->pluck('id')->sort()->values()->all();
}

beforeEach(function (): void {
    fakeReoptimizationTenant();
    buildProposalSchema();

    // O lock é uma coluna nova em shelves; o schema de teste é montado à mão.
    Schema::connection('tenant')->table('shelves', function ($table): void {
        $table->boolean('is_locked')->default(false);
    });
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('geração preserva os segments da prateleira travada', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario();
    bindDeterministicScorer($products);

    app(AutoPlanogramService::class)->generate($input);

    // Trava uma prateleira que RECEBEU produto na primeira geração.
    $lockedShelfId = Segment::query()->value('shelf_id');
    Shelf::whereKey($lockedShelfId)->update(['is_locked' => true]);

    $preservedSegments = segmentIdsOfShelf($lockedShelfId);
    $preservedLayers = Layer::whereIn('segment_id', $preservedSegments)->pluck('product_id')->sort()->values()->all();

    expect($preservedSegments)->not->toBeEmpty();

    // Regenera com as seções recarregadas (o lock precisa chegar ao motor e ao writer).
    $input->sections->each(fn ($section) => $section->load('shelves'));
    app(AutoPlanogramService::class)->generate($input);

    // Os MESMOS registros continuam lá — não foram apagados e recriados.
    expect(segmentIdsOfShelf($lockedShelfId))->toBe($preservedSegments)
        ->and(Layer::whereIn('segment_id', $preservedSegments)->pluck('product_id')->sort()->values()->all())
        ->toBe($preservedLayers);
});

/**
 * A outra metade da promessa: o produto travado sai do pool de candidatos.
 *
 * Sem isto, o motor — que não reposiciona a prateleira travada — colocaria o mesmo SKU numa
 * prateleira livre, e ele apareceria DUAS vezes na gôndola. É um erro que não quebra nada
 * visivelmente; só duplica produto.
 */
test('produtos de prateleira travada são retirados do pool de candidatos', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario();
    bindDeterministicScorer($products);

    app(AutoPlanogramService::class)->generate($input);

    $lockedShelfId = Segment::query()->value('shelf_id');
    Shelf::whereKey($lockedShelfId)->update(['is_locked' => true]);

    $expected = Layer::whereIn('segment_id', segmentIdsOfShelf($lockedShelfId))
        ->pluck('product_id')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($expected)->not->toBeEmpty();

    $gondola = new Gondola;
    $gondola->forceFill([
        'id' => $input->gondolaId,
        'planogram_id' => $input->planogramId,
        'name' => 'Gôndola travada',
    ])->save();

    $excluded = array_keys(app(LockedShelfProducts::class)->forGondola($gondola->fresh()));
    sort($excluded);

    expect($excluded)->toBe($expected);
});

test('sem prateleira travada, nenhum produto é retirado do pool', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario();
    bindDeterministicScorer($products);

    app(AutoPlanogramService::class)->generate($input);

    $gondola = new Gondola;
    $gondola->forceFill([
        'id' => $input->gondolaId,
        'planogram_id' => $input->planogramId,
        'name' => 'Gôndola livre',
    ])->save();

    expect(app(LockedShelfProducts::class)->forGondola($gondola->fresh()))->toBe([]);
});

test('travar todas as prateleiras não deixa a gôndola vazia', function (): void {
    ['input' => $input, 'products' => $products] = makeTemplateScenario();
    bindDeterministicScorer($products);

    app(AutoPlanogramService::class)->generate($input);

    $before = Segment::count();
    expect($before)->toBeGreaterThan(0);

    Shelf::query()->update(['is_locked' => true]);
    $input->sections->each(fn ($section) => $section->load('shelves'));

    app(AutoPlanogramService::class)->generate($input);

    // Nada foi apagado: com tudo travado, a geração é um no-op sobre o layout.
    expect(Segment::count())->toBe($before);
});
