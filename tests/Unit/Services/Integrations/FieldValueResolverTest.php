<?php

use App\Services\Integrations\FieldValueResolver;

it('resolves subtraction expression and applies round2 transform', function (): void {
    $resolver = new FieldValueResolver;

    $value = $resolver->resolve(
        [
            'valor_liquido' => '100.80',
            'valor_impostos' => '10.10',
            'custo_medio_loja' => '40.20',
        ],
        'valor_liquido - valor_impostos - custo_medio_loja',
        ['round2'],
    );

    expect($value)->toBe(50.5);
});

it('resolves expressions with multiplication and division using operator precedence', function (): void {
    $resolver = new FieldValueResolver;

    $value = $resolver->resolve(
        [
            'a' => 10,
            'b' => 2,
            'c' => 3,
            'd' => 4,
            'e' => 2,
        ],
        'a + b * c - d / e',
        ['round2'],
    );

    expect($value)->toBe(14.0);
});
