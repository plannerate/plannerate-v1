<?php

use App\Models\EanReference;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());
});

test('authenticated user can list ean references in landlord', function (): void {
    $response = $this->get(route('landlord.ean-references.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/ean-references/Index')
            ->has('ean_references.data'));
});

test('authenticated user can create update and delete ean reference in landlord', function (): void {
    $createResponse = $this->post(route('landlord.ean-references.store'), [
        'ean' => '7891234567890',
        'reference_description' => 'Produto base',
        'brand' => 'Marca Base',
        'width' => 10,
        'height' => 20,
        'depth' => 30,
        'unit' => 'cm',
        'dimension_status' => 'published',
    ]);

    $createResponse->assertRedirect(route('landlord.ean-references.index'));

    $reference = EanReference::query()->where('ean', '7891234567890')->firstOrFail();

    $this->assertDatabaseHas('ean_references', [
        'id' => $reference->id,
        'brand' => 'Marca Base',
        'has_dimensions' => 1,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.ean-references.update', $reference), [
        'ean' => '7891234567890',
        'reference_description' => 'Produto base atualizado',
        'brand' => 'Marca Atualizada',
        'width' => 11,
        'height' => 22,
        'depth' => 33,
        'unit' => 'cm',
        'dimension_status' => 'draft',
    ]);

    $updateResponse->assertRedirect(route('landlord.ean-references.index'));

    $this->assertDatabaseHas('ean_references', [
        'id' => $reference->id,
        'reference_description' => 'Produto base atualizado',
        'brand' => 'Marca Atualizada',
        'dimension_status' => 'draft',
    ], 'landlord');

    $deleteResponse = $this->delete(route('landlord.ean-references.destroy', $reference));

    $deleteResponse->assertRedirect(route('landlord.ean-references.index'));

    $this->assertSoftDeleted('ean_references', [
        'id' => $reference->id,
    ], 'landlord');
});
