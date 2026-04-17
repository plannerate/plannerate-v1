<?php

use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('does not error when planogram category is null', function () {
    $planogram = Planogram::factory()->create(['category_id' => null]);
    $gondola = Gondola::factory()->create(['planogram_id' => $planogram->id]);

    $response = $this->getJson(route('tenant.plannerates.editor.gondolas.products', [
        'planogram' => $planogram->id,
        'record' => $gondola->id,
    ]));

    $response->assertSuccessful();
    $response->assertJsonMissingValidationErrors();
});

it('does not error when planogram category relation is missing', function () {
    $category = Category::factory()->create();
    $planogram = Planogram::factory()->create(['category_id' => $category->id]);
    $gondola = Gondola::factory()->create(['planogram_id' => $planogram->id]);
    $category->delete(); // Remove category, relation will be null

    $response = $this->getJson(route('tenant.plannerates.editor.gondolas.products', [
        'planogram' => $planogram->id,
        'record' => $gondola->id,
    ]));

    $response->assertSuccessful();
    $response->assertJsonMissingValidationErrors();
});
