<?php

use App\Models\Editor\Gondola;
use App\Models\Planogram;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('creates a gondola with one shelf at base height', function () {
    $planogram = Planogram::factory()->create();

    $this->post(route('tenant.plannerates.editor.gondolas.store', ['planogram' => $planogram->id]), [
        'gondolaName' => 'Test Gondola',
        'location' => 'Front',
        'side' => 'left',
        'scaleFactor' => 1.0,
        'flow' => 'horizontal',
        'status' => 'draft',
        'height' => 200.0,
        'width' => 100.0,
        'baseDepth' => 50.0,
        'baseHeight' => 20.0,
        'baseWidth' => 100.0,
        'rackWidth' => 5.0,
        'holeHeight' => 2.5,
        'holeWidth' => 2.5,
        'holeSpacing' => 5.0,
        'numModules' => 1,
        'numShelves' => 1,
        'shelfWidth' => 100.0,
        'shelfHeight' => 2.0,
        'shelfDepth' => 50.0,
        'productType' => 'standard',
    ]);

    $gondola = Gondola::latest()->first();
    $shelf = $gondola->sections->first()->shelves->first();

    expect($shelf->shelf_position)->toBe(20.0);
});

it('creates a gondola with two shelves at bottom and top', function () {
    $planogram = Planogram::factory()->create();

    $this->post(route('tenant.plannerates.editor.gondolas.store', ['planogram' => $planogram->id]), [
        'gondolaName' => 'Test Gondola',
        'location' => 'Front',
        'side' => 'left',
        'scaleFactor' => 1.0,
        'flow' => 'horizontal',
        'status' => 'draft',
        'height' => 200.0,
        'width' => 100.0,
        'baseDepth' => 50.0,
        'baseHeight' => 20.0,
        'baseWidth' => 100.0,
        'rackWidth' => 5.0,
        'holeHeight' => 2.5,
        'holeWidth' => 2.5,
        'holeSpacing' => 5.0,
        'numModules' => 1,
        'numShelves' => 2,
        'shelfWidth' => 100.0,
        'shelfHeight' => 2.0,
        'shelfDepth' => 50.0,
        'productType' => 'standard',
    ]);

    $gondola = Gondola::latest()->first();
    $shelves = $gondola->sections->first()->shelves;

    expect($shelves->count())->toBe(2)
        ->and($shelves[0]->shelf_position)->toBe(20.0) // Bottom
        ->and($shelves[1]->shelf_position)->toBe(200.0); // Top
});

it('creates a gondola with multiple shelves distributed equally with first at bottom and last at top', function () {
    $planogram = Planogram::factory()->create();

    $this->post(route('tenant.plannerates.editor.gondolas.store', ['planogram' => $planogram->id]), [
        'gondolaName' => 'Test Gondola',
        'location' => 'Front',
        'side' => 'left',
        'scaleFactor' => 1.0,
        'flow' => 'horizontal',
        'status' => 'draft',
        'height' => 200.0,
        'width' => 100.0,
        'baseDepth' => 50.0,
        'baseHeight' => 20.0,
        'baseWidth' => 100.0,
        'rackWidth' => 5.0,
        'holeHeight' => 2.5,
        'holeWidth' => 2.5,
        'holeSpacing' => 5.0,
        'numModules' => 1,
        'numShelves' => 5,
        'shelfWidth' => 100.0,
        'shelfHeight' => 2.0,
        'shelfDepth' => 50.0,
        'productType' => 'standard',
    ]);

    $gondola = Gondola::latest()->first();
    $shelves = $gondola->sections->first()->shelves->sortBy('ordering')->values();

    expect($shelves->count())->toBe(5)
        ->and($shelves[0]->shelf_position)->toBe(20.0) // Bottom (base height)
        ->and($shelves[1]->shelf_position)->toBe(65.0) // 20 + (180/4 * 1)
        ->and($shelves[2]->shelf_position)->toBe(110.0) // 20 + (180/4 * 2)
        ->and($shelves[3]->shelf_position)->toBe(155.0) // 20 + (180/4 * 3)
        ->and($shelves[4]->shelf_position)->toBe(200.0); // Top (total height)
});

it('creates a gondola with four shelves distributed equally', function () {
    $planogram = Planogram::factory()->create();

    $this->post(route('tenant.plannerates.editor.gondolas.store', ['planogram' => $planogram->id]), [
        'gondolaName' => 'Test Gondola',
        'location' => 'Front',
        'side' => 'left',
        'scaleFactor' => 1.0,
        'flow' => 'horizontal',
        'status' => 'draft',
        'height' => 180.0,
        'width' => 100.0,
        'baseDepth' => 50.0,
        'baseHeight' => 30.0,
        'baseWidth' => 100.0,
        'rackWidth' => 5.0,
        'holeHeight' => 2.5,
        'holeWidth' => 2.5,
        'holeSpacing' => 5.0,
        'numModules' => 1,
        'numShelves' => 4,
        'shelfWidth' => 100.0,
        'shelfHeight' => 2.0,
        'shelfDepth' => 50.0,
        'productType' => 'standard',
    ]);

    $gondola = Gondola::latest()->first();
    $shelves = $gondola->sections->first()->shelves->sortBy('ordering')->values();

    // usableHeight = 180 - 30 = 150
    // spacing = 150 / (4-1) = 50
    expect($shelves->count())->toBe(4)
        ->and($shelves[0]->shelf_position)->toBe(30.0) // Bottom
        ->and($shelves[1]->shelf_position)->toBe(80.0) // 30 + 50
        ->and($shelves[2]->shelf_position)->toBe(130.0) // 30 + 100
        ->and($shelves[3]->shelf_position)->toBe(180.0); // Top
});
