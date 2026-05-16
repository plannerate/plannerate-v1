<?php

use App\Enums\ShelfLevel;

describe('ShelfLevel heuristic logic', function () {
    it('correctly determines shelf level from position in 5-shelf gondola', function () {
        expect(ShelfLevel::fromShelfPosition(0, 5))->toBe(ShelfLevel::High);
        expect(ShelfLevel::fromShelfPosition(1, 5))->toBe(ShelfLevel::Eye);
        expect(ShelfLevel::fromShelfPosition(2, 5))->toBe(ShelfLevel::Eye);
        expect(ShelfLevel::fromShelfPosition(3, 5))->toBe(ShelfLevel::Hand);
        expect(ShelfLevel::fromShelfPosition(4, 5))->toBe(ShelfLevel::Low);
    });

    it('correctly determines shelf level from position in 3-shelf gondola', function () {
        expect(ShelfLevel::fromShelfPosition(0, 3))->toBe(ShelfLevel::High);
        expect(ShelfLevel::fromShelfPosition(1, 3))->toBe(ShelfLevel::Eye);
        expect(ShelfLevel::fromShelfPosition(2, 3))->toBe(ShelfLevel::Low);
    });
});
