<?php

use App\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\GondolaSlotOverrideController;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaSlotOverride;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->char('template_id', 26)->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->unsignedTinyInteger('num_modulos')->default(1);
        $table->string('flow')->default('left_to_right');
        $table->string('alignment')->default('justify');
        $table->float('scale_factor')->default(1);
        $table->string('status')->default('draft');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26);
        $table->string('name')->nullable();
        $table->string('code')->nullable();
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_gondola_slot_overrides', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26)->index();
        $table->char('category_id', 26)->nullable()->index();
        $table->unsignedTinyInteger('min_facings')->nullable();
        $table->unsignedTinyInteger('max_facings')->nullable();
        $table->string('price_order')->nullable();
        $table->string('size_order')->nullable();
        $table->string('brand_exposure')->nullable();
        $table->string('flavor_exposure')->nullable();
        $table->string('space_fallback')->nullable();
        $table->string('facing_expansion')->nullable();
        $table->boolean('use_target_stock')->nullable();
        $table->string('role_override')->nullable();
        $table->unsignedSmallInteger('max_share_per_sku')->nullable();
        $table->unsignedSmallInteger('max_share_per_brand')->nullable();
        $table->unsignedSmallInteger('max_share_per_subcategory')->nullable();
        $table->unique(['gondola_id', 'category_id']);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_templates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->string('code');
        $table->string('name');
        $table->string('department')->default('geral');
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
        $table->char('category_id', 26)->nullable();
        $table->unsignedTinyInteger('module_number');
        $table->unsignedTinyInteger('shelf_order');
        $table->unsignedTinyInteger('min_facings')->nullable();
        $table->unsignedTinyInteger('max_facings')->nullable();
        $table->string('price_order')->nullable();
        $table->string('size_order')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

function makeOverrideController(): GondolaSlotOverrideController
{
    return new class extends GondolaSlotOverrideController
    {
        public function __construct() {}
    };
}

test('upsert cria override para uma categoria', function (): void {
    $tenantId = (string) Str::ulid();
    $categoryId = (string) Str::ulid();

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Gôndola Teste',
        'slug' => 'gondola-teste',
    ]);

    $request = Request::create('/api/gondolas/'.$gondola->id.'/generation-overrides', 'PUT', [
        'category_id' => $categoryId,
        'min_facings' => 2,
        'max_facings' => 8,
        'price_order' => 'asc',
    ]);

    makeOverrideController()->upsert($request, $gondola->id);

    $override = GondolaSlotOverride::where('gondola_id', $gondola->id)->first();

    expect($override)->not->toBeNull()
        ->and($override->category_id)->toBe($categoryId)
        ->and($override->getRawOriginal('min_facings'))->toBe(2)
        ->and($override->getRawOriginal('price_order'))->toBe('asc');

    expect(GondolaSlotOverride::where('gondola_id', $gondola->id)->count())->toBe(1);
});

test('upsert atualiza override existente sem criar duplicata', function (): void {
    $tenantId = (string) Str::ulid();
    $categoryId = (string) Str::ulid();

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Gôndola Teste',
        'slug' => 'gondola-teste',
    ]);

    GondolaSlotOverride::query()->create([
        'tenant_id' => $tenantId,
        'gondola_id' => $gondola->id,
        'category_id' => $categoryId,
        'min_facings' => 1,
    ]);

    $request = Request::create('/api/gondolas/'.$gondola->id.'/generation-overrides', 'PUT', [
        'category_id' => $categoryId,
        'min_facings' => 4,
        'price_order' => 'desc',
    ]);

    makeOverrideController()->upsert($request, $gondola->id);

    $override = GondolaSlotOverride::where('gondola_id', $gondola->id)->first();

    expect($override->getRawOriginal('min_facings'))->toBe(4)
        ->and($override->getRawOriginal('price_order'))->toBe('desc');

    expect(GondolaSlotOverride::where('gondola_id', $gondola->id)->count())->toBe(1);
});

test('destroy faz soft delete do override', function (): void {
    $tenantId = (string) Str::ulid();
    $categoryId = (string) Str::ulid();

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Gôndola Teste',
        'slug' => 'gondola-teste',
    ]);

    GondolaSlotOverride::query()->create([
        'tenant_id' => $tenantId,
        'gondola_id' => $gondola->id,
        'category_id' => $categoryId,
        'min_facings' => 2,
    ]);

    makeOverrideController()->destroy($gondola->id, $categoryId);

    expect(GondolaSlotOverride::where('gondola_id', $gondola->id)->count())->toBe(0);
    expect(GondolaSlotOverride::withTrashed()->where('gondola_id', $gondola->id)->count())->toBe(1);
});

test('applyToTemplate lança exceção quando gondola não tem template', function (): void {
    $tenantId = (string) Str::ulid();
    $categoryId = (string) Str::ulid();

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Gôndola Sem Template',
        'slug' => 'gondola-sem-template',
    ]);

    expect(fn () => makeOverrideController()->applyToTemplate($gondola->id, $categoryId))
        ->toThrow(ValidationException::class);
});

test('applyToTemplate atualiza slots do template com campos não-nulos do override', function (): void {
    $tenantId = (string) Str::ulid();
    $categoryId = (string) Str::ulid();

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'TMP-01',
        'name' => 'Template Teste',
        'department' => 'geral',
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenantId,
        'template_id' => $template->id,
        'code' => 'SUB-1M',
        'num_modules' => 1,
    ]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenantId,
        'template_id' => $template->id,
        'name' => 'Gôndola Teste',
        'slug' => 'gondola-teste',
        'num_modulos' => 1,
    ]);

    Section::query()->create([
        'id' => (string) Str::ulid(),
        'gondola_id' => $gondola->id,
        'ordering' => 1,
    ]);

    $slot = PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenantId,
        'subtemplate_id' => $subtemplate->id,
        'category_id' => $categoryId,
        'module_number' => 1,
        'shelf_order' => 3,
        'min_facings' => 1,
        'price_order' => 'none',
    ]);

    GondolaSlotOverride::query()->create([
        'tenant_id' => $tenantId,
        'gondola_id' => $gondola->id,
        'category_id' => $categoryId,
        'min_facings' => 3,
        'price_order' => 'asc',
    ]);

    makeOverrideController()->applyToTemplate($gondola->id, $categoryId);

    $slot->refresh();
    expect($slot->getRawOriginal('min_facings'))->toBe(3)
        ->and($slot->getRawOriginal('price_order'))->toBe('asc');
});
