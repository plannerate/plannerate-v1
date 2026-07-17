<?php

/**
 * Testes do deep copy de módulo (Section) entre gôndolas.
 *
 * Exercita SectionService::deepCopyToGondola direto contra o banco tenant (sqlite):
 * duplica section → shelves → segments → layers com novos ULIDs, preserva product_id,
 * regrava code/slug únicos, ignora linhas soft-deleted e mantém a origem intacta.
 *
 * Segue o padrão de PlanogramChangeServiceTest (schema tenant montado no beforeEach).
 */

use Callcocam\LaravelRaptorPlannerate\Services\Editor\SectionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('name')->nullable();
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('user_id', 26)->nullable();
        $table->char('gondola_id', 26);
        $table->string('name')->nullable();
        $table->string('code')->nullable()->unique();
        $table->string('slug')->nullable()->unique();
        $table->float('width')->nullable();
        $table->float('height')->nullable();
        $table->unsignedSmallInteger('num_shelves')->nullable();
        $table->float('base_height')->nullable();
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('shelves', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('section_id', 26);
        $table->string('code')->nullable()->unique();
        $table->float('shelf_position')->default(0);
        $table->float('shelf_width')->nullable();
        $table->float('shelf_height')->nullable();
        $table->float('shelf_depth')->nullable();
        $table->string('product_type')->nullable();
        $table->unsignedSmallInteger('ordering')->default(0);
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
        $table->unsignedSmallInteger('quantity')->default(1);
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('layers', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('segment_id', 26);
        $table->char('product_id', 26)->nullable();
        $table->float('height')->nullable();
        $table->unsignedSmallInteger('quantity')->default(1);
        $table->string('status')->default('published');
        $table->timestamps();
        $table->softDeletes();
    });
});

/**
 * Cenário: gôndola de ORIGEM com um módulo cheio (2 prateleiras, 2 segmentos ativos
 * + 1 segmento soft-deleted, 2 camadas), e gôndola de DESTINO com um módulo já
 * existente (ordering 1) para validar o append no fim.
 *
 * @return array<string, string>
 */
function deepCopyScenario(): array
{
    $ids = [
        'tenantId' => (string) Str::ulid(),
        'sourceGondola' => (string) Str::ulid(),
        'targetGondola' => (string) Str::ulid(),
        'sourceSection' => (string) Str::ulid(),
        'existingTargetSection' => (string) Str::ulid(),
        'shelf1' => (string) Str::ulid(),
        'shelf2' => (string) Str::ulid(),
        'segment1' => (string) Str::ulid(),
        'segment2' => (string) Str::ulid(),
        'segmentDeleted' => (string) Str::ulid(),
        'layer1' => (string) Str::ulid(),
        'layer2' => (string) Str::ulid(),
        'productX' => (string) Str::ulid(),
        'productY' => (string) Str::ulid(),
    ];

    DB::connection('tenant')->table('gondolas')->insert([
        ['id' => $ids['sourceGondola'], 'tenant_id' => $ids['tenantId'], 'name' => 'Gôndola Origem', 'created_at' => now(), 'updated_at' => now()],
        ['id' => $ids['targetGondola'], 'tenant_id' => $ids['tenantId'], 'name' => 'Gôndola Destino', 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::connection('tenant')->table('sections')->insert([
        [
            'id' => $ids['sourceSection'], 'tenant_id' => $ids['tenantId'], 'gondola_id' => $ids['sourceGondola'],
            'name' => 'Módulo 1', 'code' => 'SEC-ORIGINAL', 'slug' => 'modulo-origem', 'width' => 130, 'height' => 200,
            'num_shelves' => 2, 'base_height' => 17, 'ordering' => 1, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'id' => $ids['existingTargetSection'], 'tenant_id' => $ids['tenantId'], 'gondola_id' => $ids['targetGondola'],
            'name' => 'Módulo 1', 'code' => 'SEC-EXISTING', 'slug' => 'modulo-destino', 'width' => 130, 'height' => 200,
            'num_shelves' => 1, 'base_height' => 17, 'ordering' => 1, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    DB::connection('tenant')->table('shelves')->insert([
        ['id' => $ids['shelf1'], 'tenant_id' => $ids['tenantId'], 'section_id' => $ids['sourceSection'], 'code' => 'SHF-1', 'shelf_position' => 50, 'shelf_height' => 4, 'ordering' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $ids['shelf2'], 'tenant_id' => $ids['tenantId'], 'section_id' => $ids['sourceSection'], 'code' => 'SHF-2', 'shelf_position' => 100, 'shelf_height' => 4, 'ordering' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::connection('tenant')->table('segments')->insert([
        ['id' => $ids['segment1'], 'tenant_id' => $ids['tenantId'], 'shelf_id' => $ids['shelf1'], 'ordering' => 1, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
        ['id' => $ids['segment2'], 'tenant_id' => $ids['tenantId'], 'shelf_id' => $ids['shelf2'], 'ordering' => 1, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
        // soft-deleted: NÃO deve ser copiado
        ['id' => $ids['segmentDeleted'], 'tenant_id' => $ids['tenantId'], 'shelf_id' => $ids['shelf2'], 'ordering' => 2, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now()],
    ]);

    DB::connection('tenant')->table('layers')->insert([
        ['id' => $ids['layer1'], 'tenant_id' => $ids['tenantId'], 'segment_id' => $ids['segment1'], 'product_id' => $ids['productX'], 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $ids['layer2'], 'tenant_id' => $ids['tenantId'], 'segment_id' => $ids['segment2'], 'product_id' => $ids['productY'], 'quantity' => 3, 'created_at' => now(), 'updated_at' => now()],
    ]);

    return $ids;
}

test('deepCopyToGondola duplica o módulo inteiro na gôndola destino, no fim da ordenação', function (): void {
    $ids = deepCopyScenario();

    $newSectionId = app(SectionService::class)->deepCopyToGondola($ids['sourceSection'], $ids['targetGondola']);

    $newSection = DB::connection('tenant')->table('sections')->where('id', $newSectionId)->first();

    // Nova section: gôndola destino, append no fim (após o módulo existente → ordering 2)
    expect($newSectionId)->not->toBe($ids['sourceSection'])
        ->and($newSection)->not->toBeNull()
        ->and($newSection->gondola_id)->toBe($ids['targetGondola'])
        ->and((int) $newSection->ordering)->toBe(2)
        ->and($newSection->code)->not->toBe('SEC-ORIGINAL')
        ->and($newSection->slug)->toBeNull()
        ->and((float) $newSection->width)->toBe(130.0);

    // Filhos copiados (apenas ativos): 2 prateleiras, 2 segmentos, 2 camadas
    $newShelves = DB::connection('tenant')->table('shelves')->where('section_id', $newSectionId)->get();
    expect($newShelves)->toHaveCount(2);

    $newShelfIds = $newShelves->pluck('id')->all();
    $newSegments = DB::connection('tenant')->table('segments')->whereIn('shelf_id', $newShelfIds)->get();
    expect($newSegments)->toHaveCount(2);

    $newSegmentIds = $newSegments->pluck('id')->all();
    $newLayers = DB::connection('tenant')->table('layers')->whereIn('segment_id', $newSegmentIds)->get();
    expect($newLayers)->toHaveCount(2)
        // product_id preservado (produto é referência, não cópia)
        ->and($newLayers->pluck('product_id')->sort()->values()->all())
        ->toBe(collect([$ids['productX'], $ids['productY']])->sort()->values()->all());

    // IDs novos em todos os níveis (nenhum id da origem reaproveitado)
    expect($newShelfIds)->not->toContain($ids['shelf1'])->not->toContain($ids['shelf2'])
        ->and($newSegmentIds)->not->toContain($ids['segment1'])->not->toContain($ids['segment2']);
});

test('deepCopyToGondola não altera o módulo de origem', function (): void {
    $ids = deepCopyScenario();

    app(SectionService::class)->deepCopyToGondola($ids['sourceSection'], $ids['targetGondola']);

    $source = DB::connection('tenant')->table('sections')->where('id', $ids['sourceSection'])->first();
    expect($source->gondola_id)->toBe($ids['sourceGondola'])
        ->and((int) $source->ordering)->toBe(1)
        ->and($source->code)->toBe('SEC-ORIGINAL');

    // Origem intacta: 2 prateleiras, 2 segmentos ativos (+1 deletado), 2 camadas
    expect(DB::connection('tenant')->table('shelves')->where('section_id', $ids['sourceSection'])->count())->toBe(2)
        ->and(DB::connection('tenant')->table('segments')->whereIn('shelf_id', [$ids['shelf1'], $ids['shelf2']])->whereNull('deleted_at')->count())->toBe(2)
        ->and(DB::connection('tenant')->table('layers')->whereIn('segment_id', [$ids['segment1'], $ids['segment2']])->count())->toBe(2);
});
