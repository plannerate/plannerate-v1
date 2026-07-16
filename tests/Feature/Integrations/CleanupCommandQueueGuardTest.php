<?php

use App\Jobs\Integrations\FetchIntegrationPageJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

test('cleanup aborta quando as filas de importação têm backlog', function (): void {
    Queue::fake();

    FetchIntegrationPageJob::dispatch('01JZZZZZZZZZZZZZZZZZZZZZZZ', 'products', 1);

    $exitCode = Artisan::call('sync:cleanup');

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('cleanup abortado');
});
