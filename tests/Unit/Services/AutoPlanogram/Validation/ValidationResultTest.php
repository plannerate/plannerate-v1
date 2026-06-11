<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationResult;
use Callcocam\LaravelRaptorPlannerate\Enums\ValidationSeverity;

describe('ValidationResult', function () {
    describe('factory methods', function () {
        it('creates info result', function () {
            $result = ValidationResult::info(
                'test_rule',
                'Test message',
                ['product-1', 'product-2'],
                'shelf-1',
                'section-1',
            );

            expect($result->severity)->toBe(ValidationSeverity::Info);
            expect($result->ruleName)->toBe('test_rule');
            expect($result->message)->toBe('Test message');
            expect($result->affectedProductIds)->toBe(['product-1', 'product-2']);
            expect($result->affectedShelfId)->toBe('shelf-1');
            expect($result->affectedSectionId)->toBe('section-1');
        });

        it('creates warning result', function () {
            $result = ValidationResult::warning('test_rule', 'Warning message');

            expect($result->severity)->toBe(ValidationSeverity::Warning);
        });

        it('creates error result', function () {
            $result = ValidationResult::error('test_rule', 'Error message');

            expect($result->severity)->toBe(ValidationSeverity::Error);
        });
    });
});

describe('ValidationSeverity Enum', function () {
    describe('labels', function () {
        it('returns correct labels', function () {
            expect(ValidationSeverity::Info->label())->toBe('Informação');
            expect(ValidationSeverity::Warning->label())->toBe('Aviso');
            expect(ValidationSeverity::Error->label())->toBe('Erro');
        });
    });

    describe('colors', function () {
        it('returns correct bootstrap colors', function () {
            expect(ValidationSeverity::Info->color())->toBe('info');
            expect(ValidationSeverity::Warning->color())->toBe('warning');
            expect(ValidationSeverity::Error->color())->toBe('danger');
        });
    });

    describe('icons', function () {
        it('returns correct bootstrap icons', function () {
            expect(ValidationSeverity::Info->icon())->toBe('info-circle');
            expect(ValidationSeverity::Warning->icon())->toBe('exclamation-triangle');
            expect(ValidationSeverity::Error->icon())->toBe('x-circle');
        });
    });
});
