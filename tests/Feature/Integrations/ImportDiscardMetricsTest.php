<?php

use App\Services\Integrations\Support\ImportDiscardMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

test('acumula contadores de descarte por integração/path e por campo', function (): void {
    ImportDiscardMetrics::record('int-1', 'products', null, 10, 5, ['ean' => 3, ImportDiscardMetrics::GROUP_VALIDATION_FIELD => 2]);
    ImportDiscardMetrics::record('int-1', 'products', null, 10, 2, ['ean' => 2]);

    expect(ImportDiscardMetrics::totalForToday('int-1', 'products'))->toBe(7);

    $fieldKey = sprintf('integrations:discards:%s:%s:%s:field:ean', 'int-1', 'products', now()->toDateString());
    expect((int) Cache::get($fieldKey, 0))->toBe(5);
});

test('não registra nada quando não houve descarte', function (): void {
    ImportDiscardMetrics::record('int-2', 'sales', null, 100, 0);

    expect(ImportDiscardMetrics::totalForToday('int-2', 'sales'))->toBe(0);
});

test('página com mais da metade descartada vira Log::error', function (): void {
    Log::spy();

    ImportDiscardMetrics::record('int-3', 'products', null, 2, 8, ['ean' => 8]);

    Log::shouldHaveReceived('error')->once();
});
