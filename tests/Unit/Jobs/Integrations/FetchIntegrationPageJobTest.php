<?php

use App\Jobs\Integrations\FetchIntegrationPageJob;

it('builds a distinct overlap key for each fetch request', function (): void {
    $firstJob = new FetchIntegrationPageJob(
        integrationId: '01testintegrationid000000000',
        pathKey: 'sales',
        page: 47,
        dateStart: '2026-01-13',
        dateEnd: '2026-05-13',
        storeId: '01teststoreid000000000000000',
        storeDocument: '05318772000190',
    );

    $secondJob = new FetchIntegrationPageJob(
        integrationId: '01testintegrationid000000000',
        pathKey: 'sales',
        page: 50,
        dateStart: '2026-01-13',
        dateEnd: '2026-05-13',
        storeId: '01teststoreid000000000000000',
        storeDocument: '05318772000190',
    );

    $firstMiddleware = $firstJob->middleware()[0];
    $secondMiddleware = $secondJob->middleware()[0];

    expect($firstMiddleware->key)->not->toBe($secondMiddleware->key)
        ->and($firstMiddleware->key)->toContain('integration:01testintegrationid000000000:path:sales:page:47')
        ->and($secondMiddleware->key)->toContain('integration:01testintegrationid000000000:path:sales:page:50');
});
