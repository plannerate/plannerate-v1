<?php

use Tests\TestCase;

uses(TestCase::class);

test('redis retry after exceeds configured worker timeouts', function (): void {
    $retryAfter = (int) config('queue.connections.redis.retry_after');
    $timeouts = collect(config('horizon.defaults', []))
        ->pluck('timeout')
        ->filter(fn (mixed $timeout): bool => is_numeric($timeout))
        ->map(fn (mixed $timeout): int => (int) $timeout);

    expect($timeouts)->not->toBeEmpty()
        ->and($retryAfter)->toBeGreaterThan($timeouts->max());
});
