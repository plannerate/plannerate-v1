<?php

use Illuminate\Support\Str;

test('logs archive and clear command creates backup, clears main log and removes old archives', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'logs-clean-'.Str::uuid();
    mkdir($directory, 0777, true);

    $mainLog = $directory.DIRECTORY_SEPARATOR.'laravel.log';
    $oldArchive = $directory.DIRECTORY_SEPARATOR.'laravel-2026-01-01_000000.log';

    file_put_contents($mainLog, 'linha atual');
    file_put_contents($oldArchive, 'arquivo antigo');
    touch($oldArchive, now()->subDays(7)->timestamp);

    $this->artisan(sprintf('logs:archive-and-clear --days=5 --path=%s', $directory))
        ->assertSuccessful();

    $archives = glob($directory.DIRECTORY_SEPARATOR.'laravel-*.log') ?: [];

    expect(file_get_contents($mainLog))->toBe('')
        ->and(is_file($oldArchive))->toBeFalse()
        ->and(count($archives))->toBeGreaterThan(0);

    foreach ($archives as $archive) {
        if (is_string($archive) && is_file($archive)) {
            unlink($archive);
        }
    }
    unlink($mainLog);
    rmdir($directory);
});
