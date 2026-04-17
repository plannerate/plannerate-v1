<?php

use App\Models\Editor\Category;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('index returns usage when selected query param is provided', function () {
    $category = Category::factory()->create(['category_id' => null]);

    $response = $this->get(route('tenant.mercadologico.index', ['selected' => $category->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/mercadologico/index')
        ->has('usage')
        ->where('usage', [
            'children_count' => 0,
            'products_count' => 0,
            'planograms_count' => 0,
        ])
    );
});

it('index normalizes selected query csv to array and uses first selected for usage', function () {
    $first = Category::factory()->create(['category_id' => null]);
    $second = Category::factory()->create(['category_id' => null]);

    Category::factory()->create(['category_id' => $first->id]);

    $response = $this->get(route('tenant.mercadologico.index', ['selected' => "{$first->id},{$second->id}"]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/mercadologico/index')
        ->where('selected', [$first->id, $second->id])
        ->where('usage.children_count', 1)
    );
});

it('destroy redirects back with errors when category has children', function () {
    $parent = Category::factory()->create(['category_id' => null]);
    Category::factory()->create(['category_id' => $parent->id]);

    $response = $this->delete(route('tenant.mercadologico.destroy').'?id='.$parent->id);

    $response->assertRedirect();
    $response->assertSessionHasErrors('destroy');
    expect(Category::query()->find($parent->id))->not->toBeNull();
});

it('destroy redirects back with success and deletes category when it has no children or relations', function () {
    $category = Category::factory()->create(['category_id' => null]);

    $response = $this->delete(route('tenant.mercadologico.destroy').'?id='.$category->id);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(Category::query()->find($category->id))->toBeNull();
});
