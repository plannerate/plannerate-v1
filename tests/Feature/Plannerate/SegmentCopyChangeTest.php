<?php

use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\PlanogramChangeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('segment copy changes persist the copied segment and layer on the target shelf', function (): void {
    $tenantConnection = (string) config('multitenancy.tenant_database_connection_name', 'tenant');
    $tenantId = (string) Str::ulid();
    $userId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();
    $gondolaId = (string) Str::ulid();
    $sectionId = (string) Str::ulid();
    $sourceShelfId = (string) Str::ulid();
    $targetShelfId = (string) Str::ulid();
    $sourceSegmentId = (string) Str::ulid();
    $copiedSegmentId = (string) Str::ulid();
    $copiedLayerId = (string) Str::ulid();
    $productId = (string) Str::ulid();
    $now = now();

    $user = new User;
    $user->id = $userId;
    Auth::guard()->setUser($user);

    DB::connection($tenantConnection)->table('planograms')->insert([
        'id' => $planogramId,
        'tenant_id' => $tenantId,
        'name' => 'Planograma Teste',
        'slug' => 'planograma-teste',
        'type' => 'planograma',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('gondolas')->insert([
        'id' => $gondolaId,
        'tenant_id' => $tenantId,
        'planogram_id' => $planogramId,
        'name' => 'Gôndola Teste',
        'slug' => 'gondola-teste',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('sections')->insert([
        'id' => $sectionId,
        'tenant_id' => $tenantId,
        'gondola_id' => $gondolaId,
        'name' => 'Seção Teste',
        'code' => 'SEC-TEST',
        'slug' => 'sec-test',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('shelves')->insert([
        [
            'id' => $sourceShelfId,
            'tenant_id' => $tenantId,
            'section_id' => $sectionId,
            'code' => 'SHELF-SOURCE',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $targetShelfId,
            'tenant_id' => $tenantId,
            'section_id' => $sectionId,
            'code' => 'SHELF-TARGET',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::connection($tenantConnection)->table('products')->insert([
        'id' => $productId,
        'tenant_id' => $tenantId,
        'name' => 'Produto Teste',
        'slug' => 'produto-teste',
        'ean' => '7891164028299',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('segments')->insert([
        'id' => $sourceSegmentId,
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'shelf_id' => $sourceShelfId,
        'ordering' => 7,
        'position' => 7,
        'quantity' => 2,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $changesApplied = app(PlanogramChangeService::class)->processChanges($gondolaId, [
        [
            'type' => 'segment_copy',
            'entityType' => 'segment',
            'entityId' => $copiedSegmentId,
            'data' => [
                'source_segment_id' => $sourceSegmentId,
                'shelf_id' => $targetShelfId,
                'position' => 0,
                'layer' => [
                    'id' => $copiedLayerId,
                    'segment_id' => $copiedSegmentId,
                    'product_id' => $productId,
                    'quantity' => 3,
                    'height' => '21.00',
                    'alignment' => null,
                    'spacing' => 0,
                ],
            ],
            'timestamp' => 1778802042055,
        ],
    ]);

    expect($changesApplied)->toBe(1);

    $this->assertDatabaseHas('segments', [
        'id' => $copiedSegmentId,
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'shelf_id' => $targetShelfId,
        'ordering' => 0,
        'position' => 0,
        'quantity' => 2,
        'status' => 'published',
    ], $tenantConnection);

    $this->assertDatabaseHas('layers', [
        'id' => $copiedLayerId,
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'segment_id' => $copiedSegmentId,
        'product_id' => $productId,
        'quantity' => 3,
        'height' => '21.00',
        'spacing' => 0,
        'status' => 'published',
    ], $tenantConnection);
});
