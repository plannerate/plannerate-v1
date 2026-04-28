<?php

use App\Support\BroadcastPayload;

test('shorten error message limits payload size', function () {
    $longMessage = str_repeat('x', 1200);

    $shortMessage = BroadcastPayload::shortenErrorMessage($longMessage, 500);

    expect($shortMessage)
        ->not->toBeNull()
        ->and(strlen((string) $shortMessage))->toBeLessThanOrEqual(500)
        ->and($shortMessage)->toEndWith('...');
});

test('shorten error message returns null for empty values', function () {
    expect(BroadcastPayload::shortenErrorMessage(null))->toBeNull()
        ->and(BroadcastPayload::shortenErrorMessage('   '))->toBeNull();
});
