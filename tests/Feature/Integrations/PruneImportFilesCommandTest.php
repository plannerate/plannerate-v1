<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('remove só órfãos antigos de imports/ e quarentena antiga de imports/failed/', function (): void {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('imports/old-orphan.json', '[]');
    $disk->put('imports/recent-orphan.json', '[]');
    $disk->put('imports/failed/old-failed.json', '[]');
    $disk->put('imports/failed/recent-failed.json', '[]');

    touch($disk->path('imports/old-orphan.json'), now()->subHours(72)->getTimestamp());
    touch($disk->path('imports/failed/old-failed.json'), now()->subDays(10)->getTimestamp());

    $exitCode = Artisan::call('imports:prune');

    expect($exitCode)->toBe(Command::SUCCESS);

    $disk->assertMissing('imports/old-orphan.json');
    $disk->assertExists('imports/recent-orphan.json');
    $disk->assertMissing('imports/failed/old-failed.json');
    $disk->assertExists('imports/failed/recent-failed.json');
});

test('quarentena recente não é removida pela regra de órfãos', function (): void {
    Storage::fake('local');
    $disk = Storage::disk('local');

    // Mais velho que --hours (48h) mas mais novo que --failed-days (7d):
    // se a varredura de imports/ fosse recursiva, este arquivo seria apagado.
    $disk->put('imports/failed/three-days-old.json', '[]');
    touch($disk->path('imports/failed/three-days-old.json'), now()->subDays(3)->getTimestamp());

    Artisan::call('imports:prune');

    $disk->assertExists('imports/failed/three-days-old.json');
});
