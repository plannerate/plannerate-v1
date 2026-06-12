<?php

use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\AutoGeneratePlanogramRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Valida as regras do request de geração automática/template, em especial o
 * contrato do período de vendas: sem use_existing_analysis, start/end_date são
 * obrigatórios — protege o fluxo do stepper modo template (que não coleta
 * período; o TemplateGenerateModal é quem coleta antes de gerar).
 */

/** Payload mínimo válido — datas presentes e análise existente desligada. */
function validAutoGeneratePayload(array $overrides = []): array
{
    return array_merge([
        'strategy' => 'abc',
        'use_existing_analysis' => false,
        'start_date' => '2026-01-01',
        'end_date' => '2026-05-31',
        'min_facings' => 1,
        'max_facings' => 10,
        'group_by_subcategory' => true,
        'include_products_without_sales' => false,
        'table_type' => 'monthly_summaries',
        'category_id' => null,
        'template_id' => null,
    ], $overrides);
}

function validateAutoGeneratePayload(array $payload): Illuminate\Validation\Validator
{
    return Validator::make($payload, (new AutoGeneratePlanogramRequest)->rules());
}

test('período de vendas é obrigatório quando não usa análise existente', function (): void {
    $validator = validateAutoGeneratePayload(validAutoGeneratePayload([
        'start_date' => null,
        'end_date' => null,
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_date'))->toBeTrue()
        ->and($validator->errors()->has('end_date'))->toBeTrue();
});

test('período de vendas pode ficar vazio quando usa análise existente', function (): void {
    $validator = validateAutoGeneratePayload(validAutoGeneratePayload([
        'use_existing_analysis' => true,
        'start_date' => null,
        'end_date' => null,
    ]));

    expect($validator->fails())->toBeFalse();
});

test('payload com período válido passa na validação (fluxo template)', function (): void {
    $validator = validateAutoGeneratePayload(validAutoGeneratePayload([
        'template_id' => '01jym02qk8n1cwdq2hd5drpgsz',
    ]));

    expect($validator->fails())->toBeFalse();
});

test('data final anterior à inicial é rejeitada', function (): void {
    $validator = validateAutoGeneratePayload(validAutoGeneratePayload([
        'start_date' => '2026-05-31',
        'end_date' => '2026-01-01',
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('end_date'))->toBeTrue();
});
