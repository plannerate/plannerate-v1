<?php

use App\Services\AutoPlanogram\Template\TemplateSlotService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

test('validateSlot requer category_id', function (): void {
    $service = new TemplateSlotService;

    $request = new Request;
    $request->merge([]);

    expect(fn () => $service->validateSlot($request))
        ->toThrow(ValidationException::class);
});

test('TemplateSlotService pode ser instanciado', function (): void {
    expect(new TemplateSlotService)->toBeInstanceOf(TemplateSlotService::class);
});

/** Monta o payload mínimo válido de um slot para os testes de lote. */
function validSlotPayload(int $module, int $shelf): array
{
    return [
        'module_number' => $module,
        'shelf_order' => $shelf,
        'category_id' => null,
        'min_facings' => 1,
        'max_facings' => 5,
        'priority' => 1,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'horizontal',
        'flavor_exposure' => 'horizontal',
        'space_fallback' => 'reduce_c',
        'use_target_stock' => false,
        'facing_expansion' => 'none',
    ];
}

test('validateBulkSlots aceita lista válida de slots', function (): void {
    $service = new TemplateSlotService;

    $request = new Request;
    $request->merge([
        'slots' => [
            validSlotPayload(1, 1),
            validSlotPayload(1, 2),
        ],
    ]);

    $validated = $service->validateBulkSlots($request);

    expect($validated['slots'])->toHaveCount(2);
    expect($validated['slots'][1]['shelf_order'])->toBe(2);
});

test('validateBulkSlots exige ao menos um slot', function (): void {
    $service = new TemplateSlotService;

    $request = new Request;
    $request->merge(['slots' => []]);

    expect(fn () => $service->validateBulkSlots($request))
        ->toThrow(ValidationException::class);
});

test('validateBulkSlots rejeita max_facings menor que min_facings', function (): void {
    $service = new TemplateSlotService;

    $slot = validSlotPayload(1, 1);
    $slot['min_facings'] = 5;
    $slot['max_facings'] = 2;

    $request = new Request;
    $request->merge(['slots' => [$slot]]);

    expect(fn () => $service->validateBulkSlots($request))
        ->toThrow(ValidationException::class);
});
