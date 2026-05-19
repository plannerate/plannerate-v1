<?php

use App\Http\Controllers\AutoPlanogramController;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('planograms', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('template_id')->nullable();
        $table->string('name')->nullable();
        $table->string('slug');
        $table->string('type')->default('planograma');
        $table->string('status')->default('draft');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->char('template_id', 26)->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->unsignedTinyInteger('num_modulos')->default(1);
        $table->enum('flow', ['left_to_right', 'right_to_left'])->default('left_to_right');
        $table->enum('alignment', ['left', 'right', 'center', 'justify'])->default('justify');
        $table->float('scale_factor')->default(1);
        $table->enum('status', ['draft', 'published'])->default('draft');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('gondola_id', 26);
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_templates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->string('code');
        $table->string('name');
        $table->string('department');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_subtemplates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->char('template_id', 26);
        $table->string('code');
        $table->unsignedTinyInteger('num_modules');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_template_slots', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->char('subtemplate_id', 26);
        $table->unsignedTinyInteger('module_number');
        $table->unsignedTinyInteger('shelf_order');
        $table->string('category');
        $table->string('subcategory');
        $table->string('grouping');
        $table->string('grouping_normalized')->nullable();
        $table->unsignedTinyInteger('min_facings')->default(1);
        $table->unsignedTinyInteger('priority')->default(1);
        $table->unsignedTinyInteger('ordering')->default(1);
        $table->timestamps();
        $table->softDeletes();
    });
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
