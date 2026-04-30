<?php

use App\Services\Logs\SystemLogService;
use Illuminate\Support\Str;

test('it parses and filters system log entries', function (): void {
    $path = sys_get_temp_dir().'/'.Str::uuid().'.log';

    file_put_contents($path, implode(PHP_EOL, [
        '[2026-04-30 10:21:51] local.ERROR: SQLSTATE[42S22]: Unknown column dimensions_status',
        '#0 stack trace line',
        '[2026-04-30 10:22:51] local.INFO: Products sync skipped persistence: no valid product identity.',
        '[2026-04-30 10:23:51] local.DEBUG: generic message',
    ]));

    $service = app(SystemLogService::class);
    $entries = $service->readEntries($path, 50);

    expect($entries)->toHaveCount(3)
        ->and($entries[0]['level'])->toBe('debug')
        ->and($entries[1]['is_key_point'])->toBeTrue();

    $errorOnly = $service->filterEntries($entries, level: 'error');
    expect($errorOnly)->toHaveCount(1)
        ->and($errorOnly[0]['message'])->toContain('Unknown column');

    $keyOnly = $service->filterEntries($entries, keyOnly: true);
    expect($keyOnly)->toHaveCount(2);

    $searchOnly = $service->filterEntries($entries, search: 'sqlstate');
    expect($searchOnly)->toHaveCount(1);

    $dateRangeOnly = $service->filterEntries(
        $entries,
        from: '2026-04-30 10:22:00',
        to: '2026-04-30 10:23:59',
    );
    expect($dateRangeOnly)->toHaveCount(2);

    unlink($path);
});

test('it clears log file contents', function (): void {
    $path = sys_get_temp_dir().'/'.Str::uuid().'.log';
    file_put_contents($path, 'some log line');

    $service = app(SystemLogService::class);
    $service->clear($path);

    expect(file_get_contents($path))->toBe('');

    unlink($path);
});
