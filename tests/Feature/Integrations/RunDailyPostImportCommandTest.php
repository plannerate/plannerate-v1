<?php

use App\Jobs\Integrations\FetchIntegrationPageJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('aborta quando as filas de importação têm backlog', function (): void {
    Queue::fake();

    FetchIntegrationPageJob::dispatch('01JZZZZZZZZZZZZZZZZZZZZZZZ', 'products', 1);

    $exitCode = Artisan::call('sync:post-import', ['--wait-minutes' => 0]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('backlog');
});

test('sem backlog, segue o pipeline normalmente', function (): void {
    $exitCode = Artisan::call('sync:post-import');

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Nenhuma TenantIntegration ativa');
});

test('--skip-queue-check ignora o backlog das filas', function (): void {
    Queue::fake();

    FetchIntegrationPageJob::dispatch('01JZZZZZZZZZZZZZZZZZZZZZZZ', 'products', 1);

    $exitCode = Artisan::call('sync:post-import', ['--skip-queue-check' => true]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Nenhuma TenantIntegration ativa');
});
