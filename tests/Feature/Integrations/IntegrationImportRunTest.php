<?php

use App\Models\IntegrationImportRun;
use Illuminate\Support\Facades\Artisan;

/*
 * Valida a migration (landlord) + o model em sqlite in-memory. Nunca toca no
 * banco real (local = produção): a migration chega em produção pelo deploy.
 */
beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function runAttributes(array $overrides = []): array
{
    return array_merge([
        'tenant_id' => (string) str()->ulid(),
        'integration_id' => (string) str()->ulid(),
        'path_key' => 'sales',
        'store_id' => (string) str()->ulid(),
        'mode' => 'daily',
        'reference_date' => now()->toDateString(),
        'expected_units' => 3,
        'expected_dates' => [now()->toDateString(), now()->subDay()->toDateString(), now()->subDays(2)->toDateString()],
        'force_full' => false,
    ], $overrides);
}

test('startRun grava o plano do run com status running', function (): void {
    $run = IntegrationImportRun::startRun(runAttributes());

    expect($run->status)->toBe('running')
        ->and($run->expected_units)->toBe(3)
        ->and($run->expected_dates)->toHaveCount(3)
        ->and($run->persisted_records)->toBe(0)
        ->and($run->discovered_at)->not->toBeNull();
});

test('startRun reabre (não duplica) o run da mesma chave lógica', function (): void {
    $attrs = runAttributes(['expected_units' => 3]);
    $first = IntegrationImportRun::startRun($attrs);

    // Simula progresso e depois um novo discover no mesmo dia
    IntegrationImportRun::recordPersisted($first->id, 500);
    $second = IntegrationImportRun::startRun([...$attrs, 'expected_units' => 5]);

    expect($second->id)->toBe($first->id)                 // mesma linha
        ->and(IntegrationImportRun::count())->toBe(1)
        ->and($second->fresh()->expected_units)->toBe(5)  // plano atualizado
        ->and($second->fresh()->persisted_records)->toBe(0); // contadores reiniciados
});

test('recordPersisted acumula de forma atômica e é no-op sem run', function (): void {
    $run = IntegrationImportRun::startRun(runAttributes());

    IntegrationImportRun::recordPersisted($run->id, 500);
    IntegrationImportRun::recordPersisted($run->id, 521);
    IntegrationImportRun::recordPersisted(null, 999);   // no-op

    expect($run->fresh()->persisted_records)->toBe(1021);
});

test('recordCovered incrementa covered_units de forma atômica e é no-op sem run', function (): void {
    $run = IntegrationImportRun::startRun(runAttributes());

    IntegrationImportRun::recordCovered($run->id);
    IntegrationImportRun::recordCovered($run->id);
    IntegrationImportRun::recordCovered(null); // no-op

    expect($run->fresh()->covered_units)->toBe(2);
});

test('scopeRunningOn filtra runs do dia ainda não reconciliados', function (): void {
    IntegrationImportRun::startRun(runAttributes(['path_key' => 'sales']));
    $done = IntegrationImportRun::startRun(runAttributes(['path_key' => 'products', 'store_id' => null]));
    $done->update(['status' => 'complete']);

    $running = IntegrationImportRun::query()->runningOn(now()->toDateString())->get();

    expect($running)->toHaveCount(1)
        ->and($running->first()->path_key)->toBe('sales');
});
