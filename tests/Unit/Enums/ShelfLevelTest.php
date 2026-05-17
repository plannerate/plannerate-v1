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

        it('maps positions correctly to levels for 4 shelves', function () {
            expect(ShelfLevel::fromShelfPosition(0, 4))->toBe(ShelfLevel::High);
            expect(ShelfLevel::fromShelfPosition(1, 4))->toBe(ShelfLevel::Eye);
            expect(ShelfLevel::fromShelfPosition(2, 4))->toBe(ShelfLevel::Hand);
            expect(ShelfLevel::fromShelfPosition(3, 4))->toBe(ShelfLevel::Low);
        });

        it('maps positions correctly to levels for 5 shelves', function () {
            expect(ShelfLevel::fromShelfPosition(0, 5))->toBe(ShelfLevel::High);
            expect(ShelfLevel::fromShelfPosition(1, 5))->toBe(ShelfLevel::Eye);
            expect(ShelfLevel::fromShelfPosition(2, 5))->toBe(ShelfLevel::Eye);
            expect(ShelfLevel::fromShelfPosition(3, 5))->toBe(ShelfLevel::Hand);
            expect(ShelfLevel::fromShelfPosition(4, 5))->toBe(ShelfLevel::Low);
        });
    });

    describe('fallbackOrder', function () {
        it('produto A (EYE): aceita EYE e HAND, nunca LOW ou HIGH', function () {
            $order = ShelfLevel::Eye->fallbackOrder();
            expect($order)->toContain(ShelfLevel::Eye)
                ->and($order)->toContain(ShelfLevel::Hand)
                ->and($order)->not->toContain(ShelfLevel::Low)
                ->and($order)->not->toContain(ShelfLevel::High);
        });

        it('produto B (HAND): aceita HAND, EYE e LOW, nunca HIGH', function () {
            $order = ShelfLevel::Hand->fallbackOrder();
            expect($order)->toContain(ShelfLevel::Hand)
                ->and($order)->toContain(ShelfLevel::Eye)
                ->and($order)->toContain(ShelfLevel::Low)
                ->and($order)->not->toContain(ShelfLevel::High);
        });

        it('produto C (LOW): aceita LOW e HAND, nunca EYE ou HIGH', function () {
            $order = ShelfLevel::Low->fallbackOrder();
            expect($order)->toContain(ShelfLevel::Low)
                ->and($order)->toContain(ShelfLevel::Hand)
                ->and($order)->not->toContain(ShelfLevel::Eye)
                ->and($order)->not->toContain(ShelfLevel::High);
        });

        it('produto estratégico (HIGH): aceita HIGH e EYE, nunca HAND ou LOW', function () {
            $order = ShelfLevel::High->fallbackOrder();
            expect($order)->toContain(ShelfLevel::High)
                ->and($order)->toContain(ShelfLevel::Eye)
                ->and($order)->not->toContain(ShelfLevel::Hand)
                ->and($order)->not->toContain(ShelfLevel::Low);
        });

        it('EYE tem preferência antes de HAND no fallback de produto A', function () {
            $order = ShelfLevel::Eye->fallbackOrder();
            expect(array_search(ShelfLevel::Eye, $order))->toBeLessThan(
                array_search(ShelfLevel::Hand, $order)
            );
        });

        it('HAND tem preferência antes de EYE e LOW no fallback de produto B', function () {
            $order = ShelfLevel::Hand->fallbackOrder();
            $handRank = array_search(ShelfLevel::Hand, $order);
            expect($handRank)->toBeLessThan(array_search(ShelfLevel::Eye, $order))
                ->and($handRank)->toBeLessThan(array_search(ShelfLevel::Low, $order));
        });

        it('LOW tem preferência antes de HAND no fallback de produto C', function () {
            $order = ShelfLevel::Low->fallbackOrder();
            expect(array_search(ShelfLevel::Low, $order))->toBeLessThan(
                array_search(ShelfLevel::Hand, $order)
            );
        });
    });
});
