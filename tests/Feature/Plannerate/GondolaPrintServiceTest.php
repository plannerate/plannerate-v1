<?php

use Callcocam\LaravelRaptorPlannerate\Services\Printing\GondolaPrintService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('print payload preserves shelf product type for hook shelves', function (): void {
    $now = now();
    $planogramId = (string) Str::ulid();
    $gondolaId = (string) Str::ulid();
    $sectionId = (string) Str::ulid();
    $shelfId = (string) Str::ulid();

    DB::table('planograms')->insert([
        'id' => $planogramId,
        'name' => 'Planograma Teste',
        'slug' => 'planograma-teste',
        'type' => 'planograma',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('gondolas')->insert([
        'id' => $gondolaId,
        'planogram_id' => $planogramId,
        'name' => 'Gôndola Teste',
        'slug' => 'gondola-teste',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('sections')->insert([
        'id' => $sectionId,
        'gondola_id' => $gondolaId,
        'name' => 'Módulo Teste',
        'width' => 100,
        'height' => 200,
        'num_shelves' => 1,
        'base_height' => 17,
        'base_depth' => 40,
        'base_width' => 100,
        'cremalheira_width' => 4,
        'hole_height' => 2,
        'hole_width' => 2,
        'hole_spacing' => 2,
        'ordering' => 1,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('shelves')->insert([
        'id' => $shelfId,
        'section_id' => $sectionId,
        'product_type' => 'hook',
        'shelf_width' => 100,
        'shelf_height' => 4,
        'shelf_depth' => 40,
        'shelf_position' => 80,
        'ordering' => 1,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $data = app(GondolaPrintService::class)->prepareGondolaData($gondolaId);

    expect($data['sections'][0]['shelves'][0]['product_type'])->toBe('hook');
});
