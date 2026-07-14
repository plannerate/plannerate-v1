<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutHasher;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutSnapshotSerializer;
use Callcocam\LaravelRaptorPlannerate\Enums\LayoutChangeType;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\GondolaLayoutDiffService;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\LayoutDiffContext;

/**
 * O diff é o que o usuário lê para decidir se aprova a reotimização. Se ele mentir
 * (mudança não reportada, ou reportada onde não houve), o usuário aprova às cegas.
 *
 * Sem banco: o serviço compara estruturas puras e recebe os metadados prontos.
 */

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Prateleiras: shelf-A = módulo 1/prateleira 0, shelf-B = módulo 1/prateleira 1. */
function diffContext(): LayoutDiffContext
{
    return new LayoutDiffContext(
        shelfPositions: [
            'shelf-A' => ['module' => 1, 'shelf' => 0],
            'shelf-B' => ['module' => 1, 'shelf' => 1],
            'shelf-C' => ['module' => 2, 'shelf' => 0],
        ],
        products: [
            'p1' => ['name' => 'Produto 1', 'ean' => '111', 'image_url' => null],
            'p2' => ['name' => 'Produto 2', 'ean' => '222', 'image_url' => null],
            'p3' => ['name' => 'Produto 3', 'ean' => '333', 'image_url' => null],
        ],
    );
}

/** Um segmento com um produto. */
function seg(string $shelfId, string $productId, int $facings, int $ordering = 0, int $height = 1): PlacedSegment
{
    return new PlacedSegment(
        sectionId: 'section-1',
        shelfId: $shelfId,
        ordering: $ordering,
        position: $ordering * 10,
        width: 10 * $facings,
        distributedWidth: 10 * $facings,
        layers: collect([new PlacedLayer(productId: $productId, ean: '000', quantity: $facings, height: $height)]),
        shelfLevel: ShelfLevel::Eye,
    );
}

function runDiff(array $before, array $after, array $rejectedBefore = [], array $rejectedAfter = [])
{
    return app(GondolaLayoutDiffService::class)->diff(
        collect($before),
        collect($after),
        $rejectedBefore,
        $rejectedAfter,
        diffContext(),
    );
}

/** @return list<string> */
function changesOf($diff, string $productId): array
{
    $entry = collect($diff->entries)->firstWhere('productId', $productId);

    return $entry === null
        ? []
        : array_map(fn (LayoutChangeType $c) => $c->value, $entry->changes);
}

// ── Diff: um caso por tipo de mudança ────────────────────────────────────────

test('layouts idênticos não produzem mudanças', function (): void {
    $layout = [seg('shelf-A', 'p1', 3), seg('shelf-B', 'p2', 2)];

    $diff = runDiff($layout, $layout);

    expect($diff->hasChanges)->toBeFalse()
        ->and($diff->entries)->toBeEmpty()
        ->and($diff->summary['unchanged'])->toBe(2)
        ->and($diff->summary['total_changed'])->toBe(0);
});

test('detecta produto que entrou', function (): void {
    $diff = runDiff(
        [seg('shelf-A', 'p1', 2)],
        [seg('shelf-A', 'p1', 2), seg('shelf-B', 'p2', 1)],
    );

    expect(changesOf($diff, 'p2'))->toBe(['added'])
        ->and($diff->summary['added'])->toBe(1)
        ->and($diff->summary['products_before'])->toBe(1)
        ->and($diff->summary['products_after'])->toBe(2);
});

test('detecta produto que saiu', function (): void {
    $diff = runDiff(
        [seg('shelf-A', 'p1', 2), seg('shelf-B', 'p2', 1)],
        [seg('shelf-A', 'p1', 2)],
    );

    expect(changesOf($diff, 'p2'))->toBe(['removed'])
        ->and($diff->summary['removed'])->toBe(1);
});

test('detecta ganho de frentes', function (): void {
    $diff = runDiff([seg('shelf-A', 'p1', 2)], [seg('shelf-A', 'p1', 5)]);

    $entry = collect($diff->entries)->firstWhere('productId', 'p1');

    expect(changesOf($diff, 'p1'))->toBe(['facings_increased'])
        ->and($entry->facingsBefore)->toBe(2)
        ->and($entry->facingsAfter)->toBe(5);
});

test('detecta perda de frentes', function (): void {
    $diff = runDiff([seg('shelf-A', 'p1', 5)], [seg('shelf-A', 'p1', 2)]);

    expect(changesOf($diff, 'p1'))->toBe(['facings_decreased'])
        ->and($diff->summary['facings_decreased'])->toBe(1);
});

test('detecta mudança de prateleira', function (): void {
    $diff = runDiff([seg('shelf-A', 'p1', 2)], [seg('shelf-B', 'p1', 2)]);

    $entry = collect($diff->entries)->firstWhere('productId', 'p1');

    expect(changesOf($diff, 'p1'))->toBe(['moved'])
        ->and($entry->positionBefore)->toBe(['module' => 1, 'shelf' => 0])
        ->and($entry->positionAfter)->toBe(['module' => 1, 'shelf' => 1]);
});

test('detecta mudança de empilhamento', function (): void {
    $diff = runDiff([seg('shelf-A', 'p1', 2, height: 1)], [seg('shelf-A', 'p1', 2, height: 3)]);

    expect(changesOf($diff, 'p1'))->toBe(['stacking_changed']);
});

test('acumula múltiplas mudanças no mesmo produto', function (): void {
    // Muda de prateleira E ganha frentes: as duas coisas têm que aparecer.
    $diff = runDiff([seg('shelf-A', 'p1', 2)], [seg('shelf-B', 'p1', 4)]);

    expect(changesOf($diff, 'p1'))->toContain('facings_increased')
        ->and(changesOf($diff, 'p1'))->toContain('moved');
});

test('detecta produto que passou a ser rejeitado', function (): void {
    $diff = runDiff(
        before: [seg('shelf-A', 'p1', 2)],
        after: [],
        rejectedBefore: [],
        rejectedAfter: [['product_id' => 'p1', 'rejection_reason' => 'no_horizontal_space']],
    );

    $entry = collect($diff->entries)->firstWhere('productId', 'p1');

    // Vira rejeitado — e NÃO é reportado também como "removed" (seria a mesma notícia duas vezes).
    expect(changesOf($diff, 'p1'))->toBe(['rejected_added'])
        ->and($entry->rejectionReason)->toBe('no_horizontal_space');
});

test('detecta rejeitado que voltou para a gôndola', function (): void {
    $diff = runDiff(
        before: [],
        after: [seg('shelf-A', 'p1', 1)],
        rejectedBefore: [['product_id' => 'p1', 'rejection_reason' => 'no_horizontal_space']],
        rejectedAfter: [],
    );

    // Entrou porque deixou de ser rejeitado — não é um "added" qualquer.
    expect(changesOf($diff, 'p1'))->toBe(['rejected_resolved']);
});

test('produto que continua rejeitado não vira mudança', function (): void {
    $rejected = [['product_id' => 'p3', 'rejection_reason' => 'no_horizontal_space']];

    $diff = runDiff(
        before: [seg('shelf-A', 'p1', 2)],
        after: [seg('shelf-A', 'p1', 2)],
        rejectedBefore: $rejected,
        rejectedAfter: $rejected,
    );

    expect($diff->hasChanges)->toBeFalse();
});

// ── Serializer ───────────────────────────────────────────────────────────────

test('snapshot faz round-trip preservando o layout', function (): void {
    $serializer = new LayoutSnapshotSerializer;

    $original = collect([
        new PlacedSegment(
            sectionId: 'sec-1',
            shelfId: 'shelf-A',
            ordering: 0,
            position: 0,
            width: 30,
            distributedWidth: 32,
            layers: collect([
                new PlacedLayer(productId: 'p1', ean: '111', quantity: 3, height: 2),
                new PlacedLayer(productId: 'p2', ean: '222', quantity: 1, height: 1),
            ]),
            shelfLevel: ShelfLevel::Eye,
        ),
        // shelfLevel null tem que sobreviver ao round-trip
        new PlacedSegment(
            sectionId: 'sec-1',
            shelfId: 'shelf-B',
            ordering: 1,
            position: 30,
            width: 10,
            distributedWidth: 10,
            layers: collect([new PlacedLayer(productId: 'p3', ean: '333', quantity: 1, height: 1)]),
            shelfLevel: null,
        ),
    ]);

    $restored = $serializer->fromArray($serializer->toArray($original));

    expect($restored->count())->toBe(2)
        ->and($restored[0]->shelfId)->toBe('shelf-A')
        ->and($restored[0]->distributedWidth)->toBe(32)
        ->and($restored[0]->shelfLevel)->toBe(ShelfLevel::Eye)
        ->and($restored[0]->layers)->toHaveCount(2)
        ->and($restored[0]->layers[0]->productId)->toBe('p1')
        ->and($restored[0]->layers[0]->quantity)->toBe(3)
        ->and($restored[0]->layers[0]->height)->toBe(2)
        ->and($restored[1]->shelfLevel)->toBeNull();
});

test('snapshot de versão desconhecida é recusado', function (): void {
    expect(fn () => (new LayoutSnapshotSerializer)->fromArray(['version' => 99, 'segments' => []]))
        ->toThrow(RuntimeException::class);
});

// ── Hasher ───────────────────────────────────────────────────────────────────

test('hash ignora a ordem dos segmentos na coleção', function (): void {
    $hasher = new LayoutHasher;

    $a = collect([seg('shelf-A', 'p1', 2), seg('shelf-B', 'p2', 1, ordering: 1)]);
    $b = collect([seg('shelf-B', 'p2', 1, ordering: 1), seg('shelf-A', 'p1', 2)]);

    expect($hasher->hash($b))->toBe($hasher->hash($a));
});

test('hash muda quando o conteúdo muda', function (): void {
    $hasher = new LayoutHasher;

    $original = collect([seg('shelf-A', 'p1', 2)]);
    $moreFacings = collect([seg('shelf-A', 'p1', 3)]);
    $otherShelf = collect([seg('shelf-B', 'p1', 2)]);

    expect($hasher->hash($moreFacings))->not->toBe($hasher->hash($original))
        ->and($hasher->hash($otherShelf))->not->toBe($hasher->hash($original));
});
