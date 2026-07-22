<?php

use App\Models\IntegrationImportRun;
use App\Services\Integrations\Support\ImportRunReconciler;
use Illuminate\Support\Facades\Artisan;

/*
 * Reconciliação por covered_units (fetch-concluído) vs expected_units — sem
 * query no tenant. Só precisa da tabela landlord. Nunca toca no banco real.
 */
beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function startTestRun(int $expected, array $overrides = []): IntegrationImportRun
{
    return IntegrationImportRun::startRun(array_merge([
        'tenant_id' => (string) str()->ulid(),
        'integration_id' => (string) str()->ulid(),
        'path_key' => 'sales',
        'store_id' => (string) str()->ulid(),
        'mode' => 'daily',
        'reference_date' => now()->toDateString(),
        'expected_units' => $expected,
        'expected_dates' => null,
    ], $overrides));
}

test('complete quando covered >= expected', function (): void {
    $run = startTestRun(3);
    IntegrationImportRun::recordCovered($run->id);
    IntegrationImportRun::recordCovered($run->id);
    IntegrationImportRun::recordCovered($run->id);

    $summary = ImportRunReconciler::reconcileForDate(now()->toDateString());

    expect($summary)->toMatchArray(['reconciled' => 1, 'complete' => 1, 'partial' => 0])
        ->and($run->fresh()->status)->toBe('complete')
        ->and($run->fresh()->reconciled_at)->not->toBeNull();
});

test('partial quando covered < expected (fetch de um dia/página não rodou)', function (): void {
    $run = startTestRun(3);
    IntegrationImportRun::recordCovered($run->id);
    IntegrationImportRun::recordCovered($run->id); // só 2 de 3

    ImportRunReconciler::reconcileForDate(now()->toDateString());

    expect($run->fresh()->status)->toBe('partial')
        ->and($run->fresh()->covered_units)->toBe(2);
});

test('dia de feriado (fetch rodou, zero venda) conta como coberto → sem falso-positivo', function (): void {
    // 5 dias esperados; TODOS os 5 fetches rodaram (inclusive o do feriado, que
    // trouxe zero venda). Antes (checagem por dado) daria parcial; agora complete.
    $run = startTestRun(5);
    for ($i = 0; $i < 5; $i++) {
        IntegrationImportRun::recordCovered($run->id);
    }

    ImportRunReconciler::reconcileForDate(now()->toDateString());

    expect($run->fresh()->status)->toBe('complete')
        ->and($run->fresh()->covered_units)->toBe(5);
});

test('não reconcilia runs já concluídos', function (): void {
    $done = startTestRun(1);
    $done->update(['status' => 'complete']);

    $summary = ImportRunReconciler::reconcileForDate(now()->toDateString());

    expect($summary['reconciled'])->toBe(0);
});

test('fecha runs que ficaram para trás em datas anteriores', function (): void {
    // Importação longa que atravessa a meia-noite: o run nasce com a data de
    // ontem e o post-import do dia seguinte procurava só por hoje, deixando-o
    // 'running' para sempre — aparecendo na UI como se ainda estivesse rodando.
    $ontem = startTestRun(2, ['reference_date' => now()->subDay()->toDateString()]);
    IntegrationImportRun::recordCovered($ontem->id);
    IntegrationImportRun::recordCovered($ontem->id);

    $antigoIncompleto = startTestRun(4, ['reference_date' => now()->subDays(5)->toDateString()]);
    IntegrationImportRun::recordCovered($antigoIncompleto->id);

    $summary = ImportRunReconciler::reconcileForDate(now()->toDateString());

    expect($summary)->toMatchArray(['reconciled' => 2, 'complete' => 1, 'partial' => 1])
        ->and($ontem->fresh()->status)->toBe('complete')
        ->and($antigoIncompleto->fresh()->status)->toBe('partial');
});

test('não toca em run de data futura', function (): void {
    $futuro = startTestRun(1, ['reference_date' => now()->addDay()->toDateString()]);

    $summary = ImportRunReconciler::reconcileForDate(now()->toDateString());

    expect($summary['reconciled'])->toBe(0)
        ->and($futuro->fresh()->status)->toBe('running');
});
