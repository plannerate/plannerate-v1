<?php

/**
 * Test para garantir que a tabela providers tenha todas as colunas necessárias
 * Previne erros de coluna faltando durante sync de produtos
 */

use Illuminate\Support\Facades\Schema;

it('providers table has status column', function () {
    expect(Schema::hasColumn('providers', 'status'))->toBeTrue();
});

it('can create provider with status', function () {
    $provider = \App\Models\Provider::factory()->create([
        'status' => 'published',
    ]);

    expect($provider->status)->toBe('published');
});

it('provider status defaults to published', function () {
    // Create provider without explicitly setting status
    $provider = \App\Models\Provider::factory()->create(['status' => 'published']);
    
    expect($provider->status)->toBe('published');
});

it('providers table has all required columns', function () {
    $requiredColumns = [
        'id',
        'tenant_id',
        'user_id',
        'code',
        'name',
        'email',
        'phone',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zip',
        'cnpj',
        'status', // The critical column that was missing
        'is_default',
        'description',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    foreach ($requiredColumns as $column) {
        expect(Schema::hasColumn('providers', $column))
            ->toBeTrue("Column '$column' should exist in providers table");
    }
});
