<?php

use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor\StoreGondolaRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\GondolaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->char('template_id', 26)->nullable();
        $table->string('generation_mode')->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('location')->nullable();
        $table->string('side')->nullable();
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
        $table->integer('ordering')->default(1);
        $table->float('width')->default(0);
        $table->float('height')->default(0);
        $table->float('base_width')->default(0);
        $table->float('base_height')->default(0);
        $table->float('base_depth')->default(0);
        $table->float('cremalheira_width')->default(0);
        $table->float('hole_height')->default(0);
        $table->float('hole_width')->default(0);
        $table->float('hole_spacing')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gondolaCreateData(array $overrides = []): array
{
    return array_merge([
        'gondolaName' => 'Gôndola Teste',
        'location' => 'Corredor 1',
        'side' => 'A',
        'scaleFactor' => 1,
        'flow' => 'left_to_right',
        'status' => 'draft',
        'height' => 200,
        'width' => 100,
        'numModules' => 1,
        'baseHeight' => 10,
        'baseWidth' => 100,
        'baseDepth' => 40,
        'rackWidth' => 4,
        'holeHeight' => 2,
        'holeWidth' => 2,
        'holeSpacing' => 2,
        'shelfHeight' => 4,
        'shelfWidth' => 100,
        'shelfDepth' => 40,
        'numShelves' => 0,
        'productType' => 'normal',
    ], $overrides);
}

function gondolaTestPlanogram(): Planogram
{
    $planogram = new Planogram;
    $planogram->id = (string) Str::ulid();
    $planogram->tenant_id = (string) Str::ulid();

    return $planogram;
}

test('cria gôndola manual com generation_mode manual e template_id nulo', function (): void {
    $gondola = app(GondolaService::class)->createGondolaWithStructure(
        gondolaTestPlanogram(),
        gondolaCreateData(['mode' => 'manual']),
    );

    $row = DB::connection('tenant')->table('gondolas')->where('id', $gondola->id)->first();

    expect($row->generation_mode)->toBe('manual');
    expect($row->template_id)->toBeNull();
});

test('cria gôndola por template persistindo generation_mode e template_id', function (): void {
    $templateId = (string) Str::ulid();

    $gondola = app(GondolaService::class)->createGondolaWithStructure(
        gondolaTestPlanogram(),
        gondolaCreateData(['mode' => 'template', 'template_id' => $templateId]),
    );

    $row = DB::connection('tenant')->table('gondolas')->where('id', $gondola->id)->first();

    expect($row->generation_mode)->toBe('template');
    expect($row->template_id)->toBe($templateId);
});

test('descarta template_id quando o modo não é template', function (): void {
    $gondola = app(GondolaService::class)->createGondolaWithStructure(
        gondolaTestPlanogram(),
        gondolaCreateData(['mode' => 'automatic', 'template_id' => (string) Str::ulid()]),
    );

    $row = DB::connection('tenant')->table('gondolas')->where('id', $gondola->id)->first();

    expect($row->generation_mode)->toBe('automatic');
    expect($row->template_id)->toBeNull();
});

test('validação exige template_id e subtemplate_id quando o modo é template', function (): void {
    $rules = (new StoreGondolaRequest)->rules();

    // Sem template_id nem subtemplate_id → ambos obrigatórios
    $invalid = Validator::make(gondolaCreateData(['mode' => 'template']), $rules);
    expect($invalid->fails())->toBeTrue();
    expect($invalid->errors()->has('template_id'))->toBeTrue();
    expect($invalid->errors()->has('subtemplate_id'))->toBeTrue();

    // Com template_id mas sem o modelo (subtemplate_id) → ainda inválido
    $missingSubtemplate = Validator::make(
        gondolaCreateData(['mode' => 'template', 'template_id' => (string) Str::ulid()]),
        $rules,
    );
    expect($missingSubtemplate->fails())->toBeTrue();
    expect($missingSubtemplate->errors()->has('subtemplate_id'))->toBeTrue();

    // Template + modelo selecionados → válido
    $valid = Validator::make(
        gondolaCreateData([
            'mode' => 'template',
            'template_id' => (string) Str::ulid(),
            'subtemplate_id' => (string) Str::ulid(),
        ]),
        $rules,
    );
    expect($valid->fails())->toBeFalse();
});
