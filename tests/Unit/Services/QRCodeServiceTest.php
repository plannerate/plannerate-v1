<?php

use Callcocam\LaravelRaptorPlannerate\Services\QRCode\QRCodeService;
use Tests\TestCase;

uses(TestCase::class);

test('qr code service generates png data uri for url', function (): void {
    $dataUri = app(QRCodeService::class)->generateForUrl('https://plannerate.test/gondola/1');

    expect($dataUri)->toStartWith('data:image/png;base64,');
});

test('qr code service saves png file', function (): void {
    $path = storage_path('framework/testing/qr-code-service-test.png');

    if (file_exists($path)) {
        unlink($path);
    }

    app(QRCodeService::class)->generateToFile('https://plannerate.test/gondola/1', $path);

    expect($path)->toBeFile()
        ->and(file_get_contents($path))->toStartWith("\x89PNG");

    unlink($path);
});
