<?php

/**
 * Testes de contrato do pipeline de save-changes (delta/diff) do editor.
 *
 * Cobrem cada ChangeType que o frontend envia via POST api/editor/gondolas/{g}/save-changes,
 * exercitando PlanogramChangeService::processChanges direto contra o banco tenant (sqlite).
 *
 * Estes testes congelam o comportamento ANTES da refatoração dos services do editor
 * (Etapa 3 do plano fase-3) — qualquer reescrita precisa mantê-los verdes.
 */

use Callcocam\LaravelRaptorPlannerate\Events\LayerRemovedEvent;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\PlanogramChangeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('name')->nullable();
        $table->string('alignment')->nullable();
        $table->string('flow')->nullable();
        $table->float('scale_factor')->nullable();
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26);
        $table->string('name')->nullable();
        $table->string('code')->nullable();
        $table->float('width')->nullable();
        $table->float('height')->nullable();
        $table->unsignedSmallInteger('num_shelves')->nullable();
        $table->float('base_height')->nullable();
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->string('alignment')->nullable();
        $table->char('user_id', 26)->nullable();
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('shelves', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('section_id', 26);
        $table->string('code')->nullable();
        $table->float('shelf_position')->default(0);
        $table->float('shelf_width')->nullable();
        $table->float('shelf_height')->nullable();
        $table->float('shelf_depth')->nullable();
        $table->string('product_type')->nullable();
        $table->unsignedSmallInteger('ordering')->default(0);
        $table->string('alignment')->nullable();
        $table->float('spacing')->nullable();
        $table->char('user_id', 26)->nullable();
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('segments', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('shelf_id', 26);
        $table->float('width')->nullable();
        $table->float('height')->nullable();
        $table->unsignedSmallInteger('ordering')->default(0);
        $table->unsignedSmallInteger('position')->nullable();
        $table->string('alignment')->nullable();
        $table->float('spacing')->nullable();
        $table->unsignedSmallInteger('quantity')->default(1);
        $table->char('user_id', 26)->nullable();
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('layers', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('segment_id', 26);
        $table->char('product_id', 26);
        $table->float('height')->nullable();
        $table->string('alignment')->nullable();
        $table->float('spacing')->nullable();
        $table->unsignedSmallInteger('quantity')->default(1);
        $table->char('user_id', 26)->nullable();
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('name')->nullable();
        $table->float('width')->nullable();
        $table->float('height')->nullable();
        $table->float('depth')->nullable();
        $table->float('weight')->nullable();
        $table->string('unit')->nullable();
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });
});

/**
 * Monta o cenário base: gôndola → seção → prateleira → segment → layer → produto.
 *
 * @return array{gondolaId: string, sectionId: string, shelfId: string, segmentId: string, layerId: string, productId: string, tenantId: string}
 */
function changeScenario(): array
{
    $ids = [
        'tenantId' => (string) Str::ulid(),
        'gondolaId' => (string) Str::ulid(),
        'sectionId' => (string) Str::ulid(),
        'shelfId' => (string) Str::ulid(),
        'segmentId' => (string) Str::ulid(),
        'layerId' => (string) Str::ulid(),
        'productId' => (string) Str::ulid(),
    ];

    DB::connection('tenant')->table('gondolas')->insert([
        'id' => $ids['gondolaId'], 'tenant_id' => $ids['tenantId'],
        'name' => 'Gôndola Teste', 'alignment' => 'justify', 'flow' => 'left_to_right', 'scale_factor' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection('tenant')->table('sections')->insert([
        'id' => $ids['sectionId'], 'tenant_id' => $ids['tenantId'], 'gondola_id' => $ids['gondolaId'],
        'name' => 'Módulo 1', 'width' => 100, 'height' => 200, 'ordering' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection('tenant')->table('shelves')->insert([
        'id' => $ids['shelfId'], 'tenant_id' => $ids['tenantId'], 'section_id' => $ids['sectionId'],
        'shelf_position' => 50, 'shelf_height' => 4, 'shelf_depth' => 40, 'ordering' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection('tenant')->table('segments')->insert([
        'id' => $ids['segmentId'], 'tenant_id' => $ids['tenantId'], 'shelf_id' => $ids['shelfId'],
        'ordering' => 0, 'quantity' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection('tenant')->table('layers')->insert([
        'id' => $ids['layerId'], 'tenant_id' => $ids['tenantId'], 'segment_id' => $ids['segmentId'],
        'product_id' => $ids['productId'], 'quantity' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection('tenant')->table('products')->insert([
        'id' => $ids['productId'], 'tenant_id' => $ids['tenantId'],
        'name' => 'Produto Teste', 'width' => 10, 'height' => 20, 'depth' => 5,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $ids;
}

/** Aplica uma única mudança via processChanges e retorna quantas foram aplicadas. */
function applyChange(string $gondolaId, array $change): int
{
    return app(PlanogramChangeService::class)->processChanges($gondolaId, [$change]);
}

function tenantRow(string $table, string $id): ?object
{
    return DB::connection('tenant')->table($table)->where('id', $id)->first();
}

// ─── Shelf ───────────────────────────────────────────────────────────────────

test('shelf_update atualiza propriedades da prateleira', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'shelf_update', 'entityType' => 'shelf', 'entityId' => $ids['shelfId'],
        'data' => ['shelf_height' => 6.5, 'product_type' => 'hook'],
    ]);

    $shelf = tenantRow('shelves', $ids['shelfId']);
    expect($applied)->toBe(1)
        ->and((float) $shelf->shelf_height)->toBe(6.5)
        ->and($shelf->product_type)->toBe('hook');
});

test('shelf_update com id novo cria prateleira com segments e layers aninhados (ULID do cliente)', function (): void {
    $ids = changeScenario();
    $newShelfId = (string) Str::ulid();
    $newSegmentId = (string) Str::ulid();
    $newLayerId = (string) Str::ulid();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'shelf_update', 'entityType' => 'shelf', 'entityId' => $newShelfId,
        'data' => [
            'section_id' => $ids['sectionId'],
            'shelf_position' => 120,
            'shelf_height' => 4,
            '_is_new' => true,
            'segments' => [[
                'id' => $newSegmentId, 'ordering' => 0, 'quantity' => 1,
                'layer' => ['id' => $newLayerId, 'product_id' => $ids['productId'], 'quantity' => 3],
            ]],
        ],
    ]);

    expect($applied)->toBe(1)
        ->and(tenantRow('shelves', $newShelfId)?->section_id)->toBe($ids['sectionId'])
        ->and(tenantRow('segments', $newSegmentId)?->shelf_id)->toBe($newShelfId)
        ->and(tenantRow('layers', $newLayerId)?->segment_id)->toBe($newSegmentId)
        ->and((int) tenantRow('layers', $newLayerId)?->quantity)->toBe(3);
});

test('shelf_move atualiza shelf_position e renumera ordering por posição', function (): void {
    $ids = changeScenario();
    $secondShelfId = (string) Str::ulid();
    DB::connection('tenant')->table('shelves')->insert([
        'id' => $secondShelfId, 'tenant_id' => $ids['tenantId'], 'section_id' => $ids['sectionId'],
        'shelf_position' => 150, 'ordering' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Move a primeira prateleira para baixo da segunda
    $applied = applyChange($ids['gondolaId'], [
        'type' => 'shelf_move', 'entityType' => 'shelf', 'entityId' => $ids['shelfId'],
        'data' => ['shelf_position' => 180],
    ]);

    expect($applied)->toBe(1)
        ->and((float) tenantRow('shelves', $ids['shelfId'])->shelf_position)->toBe(180.0)
        // renumeração: a que ficou mais baixa (150) vira 1, a movida (180) vira 2
        ->and((int) tenantRow('shelves', $secondShelfId)->ordering)->toBe(1)
        ->and((int) tenantRow('shelves', $ids['shelfId'])->ordering)->toBe(2);
});

test('shelf_transfer move prateleira para outra seção', function (): void {
    $ids = changeScenario();
    $targetSectionId = (string) Str::ulid();
    DB::connection('tenant')->table('sections')->insert([
        'id' => $targetSectionId, 'tenant_id' => $ids['tenantId'], 'gondola_id' => $ids['gondolaId'],
        'ordering' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'shelf_transfer', 'entityType' => 'shelf', 'entityId' => $ids['shelfId'],
        'data' => ['to_section_id' => $targetSectionId, 'shelf_position' => 30],
    ]);

    $shelf = tenantRow('shelves', $ids['shelfId']);
    expect($applied)->toBe(1)
        ->and($shelf->section_id)->toBe($targetSectionId)
        ->and((float) $shelf->shelf_position)->toBe(30.0);
});

test('shelf_update com deleted_at faz soft delete da prateleira', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'shelf_update', 'entityType' => 'shelf', 'entityId' => $ids['shelfId'],
        'data' => ['deleted_at' => now()->toISOString()],
    ]);

    expect($applied)->toBe(1)
        ->and(tenantRow('shelves', $ids['shelfId'])->deleted_at)->not->toBeNull();
});

// ─── Section ─────────────────────────────────────────────────────────────────

test('section_update atualiza propriedades da seção', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'section_update', 'entityType' => 'section', 'entityId' => $ids['sectionId'],
        'data' => ['width' => 120.0, 'base_height' => 25.0],
    ]);

    $section = tenantRow('sections', $ids['sectionId']);
    expect($applied)->toBe(1)
        ->and((float) $section->width)->toBe(120.0)
        ->and((float) $section->base_height)->toBe(25.0);
});

test('section_update com ordering renomeia a seção para "Módulo {n}" (contrato atual)', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'section_update', 'entityType' => 'section', 'entityId' => $ids['sectionId'],
        'data' => ['ordering' => 5],
    ]);

    $section = tenantRow('sections', $ids['sectionId']);
    expect($applied)->toBe(1)
        ->and((int) $section->ordering)->toBe(5)
        ->and($section->name)->toBe('Módulo 5');
});

test('section_update com id novo cria seção (duplicar módulo)', function (): void {
    $ids = changeScenario();
    $newSectionId = (string) Str::ulid();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'section_update', 'entityType' => 'section', 'entityId' => $newSectionId,
        'data' => [
            'gondola_id' => $ids['gondolaId'], 'name' => 'Módulo 1 (Cópia)',
            'width' => 100, 'height' => 200, 'ordering' => 2, '_is_new' => true,
        ],
    ]);

    $section = tenantRow('sections', $newSectionId);
    expect($applied)->toBe(1)
        ->and($section)->not->toBeNull()
        ->and($section->name)->toBe('Módulo 1 (Cópia)')
        ->and($section->gondola_id)->toBe($ids['gondolaId']);
});

// ─── Segment ─────────────────────────────────────────────────────────────────

test('segment_update atualiza quantity (camadas verticais)', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'segment_update', 'entityType' => 'segment', 'entityId' => $ids['segmentId'],
        'data' => ['quantity' => 4],
    ]);

    expect($applied)->toBe(1)
        ->and((int) tenantRow('segments', $ids['segmentId'])->quantity)->toBe(4);
});

test('segment_update com deleted_at soft-deleta e dispara LayerRemovedEvent por layer filha', function (): void {
    Event::fake([LayerRemovedEvent::class]);
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'segment_update', 'entityType' => 'segment', 'entityId' => $ids['segmentId'],
        'data' => ['deleted_at' => now()->toISOString()],
    ]);

    expect($applied)->toBe(1)
        ->and(tenantRow('segments', $ids['segmentId'])->deleted_at)->not->toBeNull();

    Event::assertDispatched(LayerRemovedEvent::class, 1);
});

test('segment_transfer move segment para outra prateleira', function (): void {
    $ids = changeScenario();
    $targetShelfId = (string) Str::ulid();
    DB::connection('tenant')->table('shelves')->insert([
        'id' => $targetShelfId, 'tenant_id' => $ids['tenantId'], 'section_id' => $ids['sectionId'],
        'shelf_position' => 100, 'ordering' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'segment_transfer', 'entityType' => 'segment', 'entityId' => $ids['segmentId'],
        'data' => ['from_shelf_id' => $ids['shelfId'], 'to_shelf_id' => $targetShelfId, 'position' => 0],
    ]);

    expect($applied)->toBe(1)
        ->and(tenantRow('segments', $ids['segmentId'])->shelf_id)->toBe($targetShelfId);
});

test('segment_reorder atualiza ordering dentro da prateleira', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'segment_reorder', 'entityType' => 'segment', 'entityId' => $ids['segmentId'],
        'data' => ['shelf_id' => $ids['shelfId'], 'ordering' => 7],
    ]);

    expect($applied)->toBe(1)
        ->and((int) tenantRow('segments', $ids['segmentId'])->ordering)->toBe(7);
});

test('segment_copy cria segment e layer copiados na prateleira destino (Ctrl+drag)', function (): void {
    $ids = changeScenario();
    $targetShelfId = (string) Str::ulid();
    DB::connection('tenant')->table('shelves')->insert([
        'id' => $targetShelfId, 'tenant_id' => $ids['tenantId'], 'section_id' => $ids['sectionId'],
        'shelf_position' => 100, 'ordering' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $copySegmentId = (string) Str::ulid();
    $copyLayerId = (string) Str::ulid();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'segment_copy', 'entityType' => 'segment', 'entityId' => $copySegmentId,
        'data' => [
            'source_segment_id' => $ids['segmentId'],
            'shelf_id' => $targetShelfId,
            'position' => 0,
            'layer' => ['id' => $copyLayerId, 'segment_id' => $copySegmentId, 'product_id' => $ids['productId'], 'quantity' => 2],
        ],
    ]);

    expect($applied)->toBe(1)
        ->and(tenantRow('segments', $copySegmentId)?->shelf_id)->toBe($targetShelfId)
        ->and(tenantRow('layers', $copyLayerId)?->product_id)->toBe($ids['productId'])
        // original permanece intacto
        ->and(tenantRow('segments', $ids['segmentId'])->shelf_id)->toBe($ids['shelfId']);
});

// ─── Layer ───────────────────────────────────────────────────────────────────

test('layer_create cria segment + layer (adicionar produto via drag & drop)', function (): void {
    $ids = changeScenario();
    $newSegmentId = (string) Str::ulid();
    $newLayerId = (string) Str::ulid();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'layer_create', 'entityType' => 'layer', 'entityId' => $newLayerId,
        'data' => [
            'segment' => ['id' => $newSegmentId, 'shelf_id' => $ids['shelfId'], 'ordering' => 1, 'quantity' => 1],
            'layer' => ['id' => $newLayerId, 'segment_id' => $newSegmentId, 'product_id' => $ids['productId'], 'quantity' => 1],
        ],
    ]);

    expect($applied)->toBe(1)
        ->and(tenantRow('segments', $newSegmentId)?->shelf_id)->toBe($ids['shelfId'])
        ->and(tenantRow('layers', $newLayerId)?->product_id)->toBe($ids['productId']);
});

test('layer_update atualiza quantity (facings)', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'layer_update', 'entityType' => 'layer', 'entityId' => $ids['layerId'],
        'data' => ['quantity' => 9],
    ]);

    expect($applied)->toBe(1)
        ->and((int) tenantRow('layers', $ids['layerId'])->quantity)->toBe(9);
});

test('layer_update com deleted_at soft-deleta e dispara LayerRemovedEvent', function (): void {
    Event::fake([LayerRemovedEvent::class]);
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'layer_update', 'entityType' => 'layer', 'entityId' => $ids['layerId'],
        'data' => ['deleted_at' => now()->toISOString()],
    ]);

    expect($applied)->toBe(1)
        ->and(tenantRow('layers', $ids['layerId'])->deleted_at)->not->toBeNull();

    Event::assertDispatched(LayerRemovedEvent::class, 1);
});

// ─── Product ─────────────────────────────────────────────────────────────────

test('product_update com product_dimension atualiza dimensões na tabela products', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'product_update', 'entityType' => 'product', 'entityId' => $ids['productId'],
        'data' => ['product_dimension' => ['width' => 12.5, 'height' => 22, 'depth' => 6]],
    ]);

    $product = tenantRow('products', $ids['productId']);
    expect($applied)->toBe(1)
        ->and((float) $product->width)->toBe(12.5)
        ->and((float) $product->height)->toBe(22.0)
        ->and((float) $product->depth)->toBe(6.0)
        // unit default aplicado quando o produto não tem
        ->and($product->unit)->toBe('cm');
});

// ─── Gondola ─────────────────────────────────────────────────────────────────

test('gondola_update atualiza propriedades gerais', function (): void {
    $ids = changeScenario();

    $applied = applyChange($ids['gondolaId'], [
        'type' => 'gondola_update', 'entityType' => 'gondola', 'entityId' => $ids['gondolaId'],
        'data' => ['name' => 'Gôndola Renomeada', 'alignment' => 'left'],
    ]);

    $gondola = tenantRow('gondolas', $ids['gondolaId']);
    expect($applied)->toBe(1)
        ->and($gondola->name)->toBe('Gôndola Renomeada')
        ->and($gondola->alignment)->toBe('left');
});

test('gondola_scale, gondola_alignment e gondola_flow atualizam os campos específicos', function (): void {
    $ids = changeScenario();

    $applied = app(PlanogramChangeService::class)->processChanges($ids['gondolaId'], [
        ['type' => 'gondola_scale', 'entityType' => 'gondola', 'entityId' => $ids['gondolaId'], 'data' => ['scale_factor' => 4.5]],
        ['type' => 'gondola_alignment', 'entityType' => 'gondola', 'entityId' => $ids['gondolaId'], 'data' => ['alignment' => 'center']],
        ['type' => 'gondola_flow', 'entityType' => 'gondola', 'entityId' => $ids['gondolaId'], 'data' => ['flow' => 'right_to_left']],
    ]);

    $gondola = tenantRow('gondolas', $ids['gondolaId']);
    expect($applied)->toBe(3)
        ->and((float) $gondola->scale_factor)->toBe(4.5)
        ->and($gondola->alignment)->toBe('center')
        ->and($gondola->flow)->toBe('right_to_left');
});

// ─── Pipeline ────────────────────────────────────────────────────────────────

test('processChanges conta apenas mudanças aplicadas e ignora entityType desconhecido', function (): void {
    $ids = changeScenario();

    $applied = app(PlanogramChangeService::class)->processChanges($ids['gondolaId'], [
        ['type' => 'layer_update', 'entityType' => 'layer', 'entityId' => $ids['layerId'], 'data' => ['quantity' => 5]],
        ['type' => 'whatever', 'entityType' => 'desconhecido', 'entityId' => 'x', 'data' => []],
    ]);

    expect($applied)->toBe(1);
});

test('processChanges atualiza o updated_at da gôndola (touch)', function (): void {
    $ids = changeScenario();
    DB::connection('tenant')->table('gondolas')->where('id', $ids['gondolaId'])
        ->update(['updated_at' => now()->subDay()]);
    $before = tenantRow('gondolas', $ids['gondolaId'])->updated_at;

    app(PlanogramChangeService::class)->processChanges($ids['gondolaId'], [
        ['type' => 'layer_update', 'entityType' => 'layer', 'entityId' => $ids['layerId'], 'data' => ['quantity' => 5]],
    ]);

    expect(tenantRow('gondolas', $ids['gondolaId'])->updated_at)->not->toBe($before);
});
