<?php

use App\Models\EanReference;
use App\Models\SimilarGroup;
use App\Services\EanReferenceSimilarSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $landlordPath = database_path('testing_ean_reference_similar_sync.sqlite');
    if (! file_exists($landlordPath)) {
        touch($landlordPath);
    }

    Config::set('database.connections.landlord', [
        'driver' => 'sqlite',
        'database' => $landlordPath,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge('landlord');

    Schema::connection('landlord')->dropIfExists('ean_references');
    Schema::connection('landlord')->create('ean_references', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('ean')->unique();
        $table->json('metadata')->nullable();
        $table->string('unit')->default('cm');
        $table->boolean('has_dimensions')->default(false);
        $table->string('dimension_status')->default('published');
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });
});

test('sync stores similar eans under grouper code in ean reference metadata', function (): void {
    EanReference::query()->create([
        'ean' => '7891000000010',
        'metadata' => [
            'origem' => 'manual',
        ],
    ]);

    $group = new SimilarGroup([
        'grouper_code' => 'SAB-LAV-90',
    ]);

    app(EanReferenceSimilarSyncService::class)->sync($group, [
        '7891000000010',
        '7891000000027',
        '7891000000034',
    ]);

    $referenceA = EanReference::query()->where('ean', '7891000000010')->firstOrFail();
    $referenceB = EanReference::query()->where('ean', '7891000000027')->firstOrFail();

    expect($referenceA->metadata)->toMatchArray([
        'origem' => 'manual',
        'similares' => [
            'SAB-LAV-90' => [
                '7891000000027',
                '7891000000034',
            ],
        ],
    ]);
    expect($referenceB->metadata['similares']['SAB-LAV-90'])->toBe([
        '7891000000010',
        '7891000000034',
    ]);
});

test('sync removes stale grouper codes from previous eans', function (): void {
    foreach (['7891000000010', '7891000000027', '7891000000034'] as $ean) {
        EanReference::query()->create([
            'ean' => $ean,
            'metadata' => [
                'similares' => [
                    'OLD-CODE' => ['999'],
                    'SAB-LAV-90' => ['7891000000010', '7891000000027', '7891000000034'],
                ],
            ],
        ]);
    }

    $group = new SimilarGroup([
        'grouper_code' => 'SAB-LAV-100',
    ]);

    app(EanReferenceSimilarSyncService::class)->sync(
        $group,
        ['7891000000010', '7891000000027'],
        'SAB-LAV-90',
        ['7891000000010', '7891000000027', '7891000000034'],
    );

    $keptReference = EanReference::query()->where('ean', '7891000000010')->firstOrFail();
    $removedReference = EanReference::query()->where('ean', '7891000000034')->firstOrFail();

    expect($keptReference->metadata['similares'])->toHaveKey('SAB-LAV-100')
        ->and($keptReference->metadata['similares'])->not->toHaveKey('SAB-LAV-90')
        ->and($keptReference->metadata['similares'])->toHaveKey('OLD-CODE');
    expect($removedReference->metadata['similares'])->not->toHaveKey('SAB-LAV-90')
        ->and($removedReference->metadata['similares'])->not->toHaveKey('SAB-LAV-100')
        ->and($removedReference->metadata['similares'])->toHaveKey('OLD-CODE');
});
