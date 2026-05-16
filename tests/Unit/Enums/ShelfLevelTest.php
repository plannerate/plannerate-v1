<?php

use App\Enums\ShelfLevel;

describe('ShelfLevel Enum', function () {
    describe('label', function () {
        it('returns correct labels for all shelf levels', function () {
            expect(ShelfLevel::Eye->label())->toBe('Nível dos olhos');
            expect(ShelfLevel::Hand->label())->toBe('Nível das mãos');
            expect(ShelfLevel::Low->label())->toBe('Nível do chão');
            expect(ShelfLevel::High->label())->toBe('Nível alto');
        });
    });

    describe('color', function () {
        it('returns correct colors for all shelf levels', function () {
            expect(ShelfLevel::Eye->color())->toBe('success');
            expect(ShelfLevel::Hand->color())->toBe('info');
            expect(ShelfLevel::Low->color())->toBe('warning');
            expect(ShelfLevel::High->color())->toBe('secondary');
        });
    });

    describe('priorityScore', function () {
        it('returns priority scores in correct order', function () {
            expect(ShelfLevel::Eye->priorityScore())->toBe(100);
            expect(ShelfLevel::Hand->priorityScore())->toBe(80);
            expect(ShelfLevel::Low->priorityScore())->toBe(40);
            expect(ShelfLevel::High->priorityScore())->toBe(20);
        });
    });

    describe('fromShelfPosition', function () {
        it('maps positions correctly to levels for 3 shelves', function () {
            expect(ShelfLevel::fromShelfPosition(0, 3))->toBe(ShelfLevel::High);
            expect(ShelfLevel::fromShelfPosition(1, 3))->toBe(ShelfLevel::Eye);
            expect(ShelfLevel::fromShelfPosition(2, 3))->toBe(ShelfLevel::Low);
        });

        it('maps positions correctly to levels for 5 shelves', function () {
            expect(ShelfLevel::fromShelfPosition(0, 5))->toBe(ShelfLevel::High);
            expect(ShelfLevel::fromShelfPosition(1, 5))->toBe(ShelfLevel::Eye);
            expect(ShelfLevel::fromShelfPosition(2, 5))->toBe(ShelfLevel::Eye);
            expect(ShelfLevel::fromShelfPosition(3, 5))->toBe(ShelfLevel::Hand);
            expect(ShelfLevel::fromShelfPosition(4, 5))->toBe(ShelfLevel::Low);
        });
    });
});
