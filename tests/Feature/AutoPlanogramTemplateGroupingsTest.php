<?php

use App\Http\Controllers\AutoPlanogramController;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function makeAutoPlanogramControllerForTemplateGroupingTest(): AutoPlanogramController
{
    return new class extends AutoPlanogramController
    {
        public function __construct() {}
    };
}

test('templateGroupings returns unique grouping list for gondola template', function (): void {
    $tenantId = (string) Str::ulid();

    $planogram = Planogram::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Planograma Teste',
        'slug' => 'planograma-teste',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'TMP-01',
        'name' => 'Template 01',
        'department' => 'Mercearia',
        'is_active' => true,
    ]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenantId,
        'planogram_id' => $planogram->id,
        'template_id' => $template->id,
        'name' => 'Gondola Teste',
        'num_modulos' => 2,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'draft',
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenantId,
        'template_id' => $template->id,
        'code' => 'TMP-01-2M',
        'num_modules' => 2,
        'is_active' => true,
    ]);

    PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenantId,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 1,
        'category' => 'CAT',
        'subcategory' => 'SUB',
        'grouping' => 'Bebidas',
        'grouping_normalized' => 'bebidas',
        'min_facings' => 1,
        'priority' => 1,
        'ordering' => 1,
    ]);

    PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenantId,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 2,
        'shelf_order' => 2,
        'category' => 'CAT',
        'subcategory' => 'SUB',
        'grouping' => 'Bebidas',
        'grouping_normalized' => 'bebidas',
        'min_facings' => 1,
        'priority' => 1,
        'ordering' => 2,
    ]);

    PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenantId,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 3,
        'category' => 'CAT',
        'subcategory' => 'SUB',
        'grouping' => 'Snacks',
        'grouping_normalized' => 'snacks',
        'min_facings' => 1,
        'priority' => 1,
        'ordering' => 3,
    ]);

    $controller = makeAutoPlanogramControllerForTemplateGroupingTest();

    $response = $controller->templateGroupings(new Request, 'tenant-a', $gondola->id);

    $payload = $response->getData(true);

    expect($payload['data'])->toHaveCount(2)
        ->and($payload['meta']['template_id'])->toBe($template->id)
        ->and($payload['meta']['subtemplate_id'])->toBe($subtemplate->id)
        ->and($payload['data'][0]['grouping'])->toBe('Bebidas')
        ->and($payload['data'][0]['grouping_normalized'])->toBe('bebidas')
        ->and($payload['data'][0]['slots_count'])->toBe(2)
        ->and($payload['data'][1]['grouping'])->toBe('Snacks')
        ->and($payload['data'][1]['grouping_normalized'])->toBe('snacks')
        ->and($payload['data'][1]['slots_count'])->toBe(1);
});

test('templateGroupings returns empty data when gondola has no template', function (): void {
    $tenantId = (string) Str::ulid();

    $planogram = Planogram::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Planograma Sem Template',
        'slug' => 'planograma-sem-template',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenantId,
        'planogram_id' => $planogram->id,
        'template_id' => null,
        'name' => 'Gondola Sem Template',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'draft',
    ]);

    $controller = makeAutoPlanogramControllerForTemplateGroupingTest();

    $response = $controller->templateGroupings(new Request, 'tenant-a', $gondola->id);

    $payload = $response->getData(true);

    expect($payload['data'])->toBe([])
        ->and($payload['meta']['template_id'])->toBeNull()
        ->and($payload['meta']['subtemplate_id'])->toBeNull();
});
