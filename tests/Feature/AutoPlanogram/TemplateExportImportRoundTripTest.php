<?php

use App\Models\Category;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template\TemplateExportService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template\TemplateImportService;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Round-trip export → import dos templates de planograma, cobrindo os campos
 * novos (Q–AB): max_facings, priority, facing_expansion, role_override,
 * limites de participação, visual_criteria e as configurações globais do
 * subtemplate (layout_orientation, flow_direction, zonas térmicas).
 */
beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('categories', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('user_id', 26)->nullable();
        $table->char('category_id', 26)->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('full_path')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

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
        $table->json('slot_defaults')->nullable();
        $table->string('hot_zone_priority')->nullable();
        $table->string('cold_zone_priority')->nullable();
        $table->string('flow_direction')->nullable();
        $table->string('layout_orientation')->nullable();
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
        $table->string('price_order')->default('none');
        $table->string('size_order')->default('none');
        $table->string('brand_exposure')->default('mixed');
        $table->string('flavor_exposure')->default('mixed');
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

const ROUND_TRIP_TENANT = '01jym02qk8n1cwdq2hd5drpgsz';

/** Cria template completo com subtemplate configurado e um slot usando todos os campos novos. */
function makeExportableTemplate(): PlanogramTemplate
{
    $category = Category::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => ROUND_TRIP_TENANT,
        'name' => 'CERVEJAS',
        'full_path' => 'BEBIDAS|CERVEJAS',
    ]);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => ROUND_TRIP_TENANT,
        'code' => 'BEBIDAS-RT',
        'name' => 'BEBIDAS-RT',
        'department' => 'BEBIDAS',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => ROUND_TRIP_TENANT,
        'template_id' => $template->id,
        'code' => 'BEBIDAS-RT-2M',
        'num_modules' => 2,
        'is_active' => true,
        'layout_orientation' => 'vertical',
        'flow_direction' => 'right_to_left',
        'hot_zone_priority' => 'maior_margem',
        'cold_zone_priority' => 'maior_volume',
    ]);

    PlanogramTemplateSlot::query()->create([
        'tenant_id' => ROUND_TRIP_TENANT,
        'subtemplate_id' => $subtemplate->id,
        'category_id' => $category->id,
        'module_number' => 1,
        'shelf_order' => 2,
        'min_facings' => 2,
        'max_facings' => 8,
        'priority' => 3,
        'price_order' => 'desc',
        'size_order' => 'asc',
        'brand_exposure' => 'vertical',
        'flavor_exposure' => 'horizontal',
        'space_fallback' => 'remove_dog',
        'use_target_stock' => true,
        'facing_expansion' => 'target_stock',
        'role_override' => 'destino',
        'visual_criteria' => [
            ['key' => 'score_abc', 'direction' => 'desc'],
            ['key' => 'marca', 'direction' => 'asc'],
        ],
        'max_share_per_sku' => 25,
        'max_share_per_brand' => 50,
        'max_share_per_subcategory' => 80,
        'ordering' => 1,
    ]);

    return $template;
}

/** Exporta o template para um xlsx temporário e devolve o caminho. */
function exportTemplateToFile(PlanogramTemplate $template): string
{
    $response = app(TemplateExportService::class)->exportTemplate($template);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    $path = tempnam(sys_get_temp_dir(), 'tpl_rt_').'.xlsx';
    file_put_contents($path, $content);

    return $path;
}

test('export → import preserva campos novos do slot e configurações do subtemplate', function (): void {
    $template = makeExportableTemplate();
    $path = exportTemplateToFile($template);

    // Limpa tudo para garantir que o estado final vem do import
    PlanogramTemplateSlot::withoutGlobalScopes()->forceDelete();
    PlanogramSubtemplate::withoutGlobalScopes()->forceDelete();
    PlanogramTemplate::withoutGlobalScopes()->forceDelete();

    $report = app(TemplateImportService::class)->import($path, ROUND_TRIP_TENANT);
    unlink($path);

    expect($report->slotsCreated)->toBe(1);

    $subtemplate = PlanogramSubtemplate::withoutGlobalScopes()->firstOrFail();
    expect($subtemplate->layout_orientation?->value)->toBe('vertical')
        ->and($subtemplate->flow_direction?->value)->toBe('right_to_left')
        ->and($subtemplate->hot_zone_priority?->value)->toBe('maior_margem')
        ->and($subtemplate->cold_zone_priority?->value)->toBe('maior_volume');

    $slot = PlanogramTemplateSlot::withoutGlobalScopes()->firstOrFail();
    expect($slot->module_number)->toBe(1)
        ->and($slot->shelf_order)->toBe(2)
        ->and($slot->min_facings)->toBe(2)
        ->and($slot->max_facings)->toBe(8)
        ->and($slot->priority)->toBe(3)
        ->and($slot->price_order->value)->toBe('desc')
        ->and($slot->size_order->value)->toBe('asc')
        ->and($slot->brand_exposure->value)->toBe('vertical')
        ->and($slot->flavor_exposure->value)->toBe('horizontal')
        ->and($slot->space_fallback->value)->toBe('remove_dog')
        ->and($slot->use_target_stock)->toBeTrue()
        ->and($slot->facing_expansion?->value)->toBe('target_stock')
        ->and($slot->role_override?->value)->toBe('destino')
        ->and($slot->max_share_per_sku)->toBe(25)
        ->and($slot->max_share_per_brand)->toBe(50)
        ->and($slot->max_share_per_subcategory)->toBe(80)
        ->and($slot->visual_criteria)->toBe([
            ['key' => 'score_abc', 'direction' => 'desc'],
            ['key' => 'marca', 'direction' => 'asc'],
        ])
        ->and($slot->category_id)->not->toBeNull();
});

test('import de planilha legada (A–P) não sobrescreve configurações do subtemplate', function (): void {
    $template = makeExportableTemplate();

    // Planilha no formato antigo: só colunas A–P, sem os campos novos
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Templates');
    $sheet->fromArray([
        ['Código template', 'Departamento', 'Código subtemplate', 'Quantidade de módulos', 'Módulo', 'Posição prateleira', 'Categoria (caminho)', '', 'Categoria (nome)', 'Frentes por SKU', 'Ordem preço', 'Ordem tamanho', 'Exposição marca', 'Exposição sabor', 'Falta espaço', 'Estoque alvo?'],
        ['BEBIDAS-RT', 'BEBIDAS', 'BEBIDAS-RT-2M', 2, 1, 2, 'BEBIDAS|CERVEJAS', '', 'CERVEJAS', 3, '', '', 'Vertical', 'Horizontal', '', 'Sim'],
    ], null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'tpl_legacy_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    app(TemplateImportService::class)->import($path, ROUND_TRIP_TENANT);
    unlink($path);

    // Configurações globais preservadas (arquivo legado não tem colunas Y–AB)
    $subtemplate = PlanogramSubtemplate::withoutGlobalScopes()->firstOrFail();
    expect($subtemplate->layout_orientation?->value)->toBe('vertical')
        ->and($subtemplate->flow_direction?->value)->toBe('right_to_left')
        ->and($subtemplate->hot_zone_priority?->value)->toBe('maior_margem')
        ->and($subtemplate->cold_zone_priority?->value)->toBe('maior_volume');

    // Slot atualizado pelo legado: min_facings muda, priority volta ao fallback 1
    $slot = PlanogramTemplateSlot::withoutGlobalScopes()->firstOrFail();
    expect($slot->min_facings)->toBe(3)
        ->and($slot->priority)->toBe(1);
});
