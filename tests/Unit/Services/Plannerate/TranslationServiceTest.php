<?php

use Callcocam\LaravelRaptor\Services\TranslationService;

uses(Tests\TestCase::class);

it('writes translation json to a custom output path', function () {
    $temporaryDirectory = storage_path('framework/testing/translation-service');

    if (is_dir($temporaryDirectory)) {
        $files = glob($temporaryDirectory.'/*.json') ?: [];

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    @mkdir($temporaryDirectory, 0755, true);

    $outputPath = $temporaryDirectory.'/pt_BR.json';

    $service = \Mockery::mock(TranslationService::class)->makePartial();
    $service->shouldReceive('getAllTranslations')
        ->once()
        ->with('pt_BR', null)
        ->andReturn([
            'hello' => 'Ola',
            'bye' => 'Tchau',
        ]);

    $generatedPath = $service->generateJsonFile('pt_BR', null, $outputPath);

    expect($generatedPath)->toBe($outputPath);
    expect(file_exists($outputPath))->toBeTrue();

    $contents = json_decode((string) file_get_contents($outputPath), true);

    expect($contents)->toBe([
        'bye' => 'Tchau',
        'hello' => 'Ola',
    ]);
});
