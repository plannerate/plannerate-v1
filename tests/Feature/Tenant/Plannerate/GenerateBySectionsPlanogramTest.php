<?php

use App\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use App\DTOs\Plannerate\SectionGenerate\SectionGenerateResultDTO;
use App\Models\User;
use App\Services\Plannerate\SectionGenerate\SectionPlanogramService;
use Callcocam\LaravelRaptor\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['plannerate.features.auto_generate' => true]);
    Storage::fake('local');

    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->withoutMiddleware(TenantMiddleware::class);
});

$validPayload = [
    'strategy' => 'abc',
    'use_existing_analysis' => true,
    'start_date' => null,
    'end_date' => null,
    'min_facings' => 1,
    'max_facings' => 10,
    'group_by_subcategory' => true,
    'include_products_without_sales' => false,
    'table_type' => 'monthly_summaries',
];

it('returns success when generating by sections without IA', function () use ($validPayload) {
    $gondolaId = '01'.fake()->regexify('[0-9a-z]{24}');
    $resultDto = new SectionGenerateResultDTO(
        sectionsProcessed: 2,
        totalAllocated: 10,
        totalUnallocated: 1,
        generatedAt: now()->toIso8601String(),
        qualityMetrics: [
            'fill_rate' => 90.91,
            'unallocated_rate' => 9.09,
            'allocation_concentration_rate' => 60.0,
        ],
    );

    $mock = mock(SectionPlanogramService::class);
    $mock->shouldReceive('generateBySections')
        ->once()
        ->with(
            $gondolaId,
            \Mockery::on(fn ($config) => $config instanceof AutoGenerateConfigDTO),
            false
        )
        ->andReturn($resultDto);

    $this->app->instance(SectionPlanogramService::class, $mock);

    $response = $this->post(route('api.tenant.plannerate.gondolas.generate-by-sections', [
        'gondola' => $gondolaId,
    ]), $validPayload);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertSessionHas('generation_result_json_path');
    expect(session('success'))->toContain('Sections processadas: 2')
        ->toContain('Produtos alocados: 10')
        ->toContain('Produtos não alocados: 1')
        ->toContain('Métricas de qualidade:')
        ->toContain('Fill rate: 90,91%')
        ->toContain('Taxa não alocados: 9,09%')
        ->toContain('Concentração por section: 60,00%')
        ->toContain('JSON completo: storage/app/');

    $jsonPath = session('generation_result_json_path');
    expect($jsonPath)->toBeString();
    Storage::disk('local')->assertExists($jsonPath);

    $jsonPayload = json_decode(Storage::disk('local')->get($jsonPath), true);
    expect($jsonPayload)->toBeArray()
        ->and($jsonPayload['meta']['mode'])->toBe('generate-by-sections')
        ->and($jsonPayload['meta']['gondola_id'])->toBe($gondolaId)
        ->and($jsonPayload['result']['sectionsProcessed'])->toBe(2)
        ->and($jsonPayload['result']['totalAllocated'])->toBe(10);
});

it('passes use_ai true to service when request has use_ai true', function () use ($validPayload) {
    $gondolaId = '01'.fake()->regexify('[0-9a-z]{24}');
    $resultDto = new SectionGenerateResultDTO(
        sectionsProcessed: 1,
        totalAllocated: 5,
        totalUnallocated: 0,
        generatedAt: now()->toIso8601String(),
    );

    $mock = mock(SectionPlanogramService::class);
    $mock->shouldReceive('generateBySections')
        ->once()
        ->with(
            $gondolaId,
            \Mockery::on(fn ($config) => $config instanceof AutoGenerateConfigDTO),
            true
        )
        ->andReturn($resultDto);

    $this->app->instance(SectionPlanogramService::class, $mock);

    $response = $this->post(route('api.tenant.plannerate.gondolas.generate-by-sections', [
        'gondola' => $gondolaId,
    ]), array_merge($validPayload, ['use_ai' => true]));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertSessionHas('generation_result_json_path');
});

it('returns validation error when required fields are missing', function () {
    $gondolaId = '01'.fake()->regexify('[0-9a-z]{24}');

    $response = $this->post(route('api.tenant.plannerate.gondolas.generate-by-sections', [
        'gondola' => $gondolaId,
    ]), []);

    $response->assertSessionHasErrors();
});
