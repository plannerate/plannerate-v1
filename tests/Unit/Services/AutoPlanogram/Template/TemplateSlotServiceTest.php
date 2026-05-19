<?php

use App\Services\AutoPlanogram\Template\TemplateSlotService;

test('normalizeGrouping converte grouping em slug', function (): void {
    $service = new TemplateSlotService;

    expect($service->normalizeGrouping('CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA'))
        ->toBe('cereais-farinaceos-farofa-de-mandioca');
});

test('normalizeGrouping remove espaços extras e acentos', function (): void {
    $service = new TemplateSlotService;

    expect($service->normalizeGrouping('  HIGIENE   PESSOAL  '))
        ->toBe('higiene-pessoal');
});
