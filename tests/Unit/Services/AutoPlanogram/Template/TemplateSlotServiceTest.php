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
