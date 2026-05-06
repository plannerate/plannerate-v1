<?php

use App\Jobs\ProcessEanReferenceImageJob;
use Spatie\Multitenancy\Jobs\NotTenantAware;

test('process ean reference image job is explicitly not tenant aware', function (): void {
    $job = new ProcessEanReferenceImageJob(
        eanReferenceId: '01JTKQ5GW9BN5YQ7M66Q0JV0ZX',
    );

    expect($job)->toBeInstanceOf(NotTenantAware::class);
});
