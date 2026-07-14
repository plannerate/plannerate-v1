<?php

use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\SalesWindowShifter;
use Carbon\CarbonImmutable;

/**
 * A janela de vendas é a ÚNICA coisa que muda entre a geração original e a reotimização.
 * Se ela não andar, o reprocessamento devolve o mesmo planograma e a feature não tem propósito;
 * se andar errado, o planograma é reconstruído sobre o período errado do ano.
 */
function shift(array $config, string $configuredAt, string $now): array
{
    return (new SalesWindowShifter)->shift(
        $config,
        CarbonImmutable::parse($configuredAt),
        CarbonImmutable::parse($now),
    );
}

test('preserva a defasagem: janela sazonal do ano anterior continua sendo do ano anterior', function (): void {
    // O usuário gerou em jan/2026 usando jan–mar/2025 (mesmo período, ano anterior — sazonalidade).
    // Ao reprocessar em abril, a intenção "o análogo sazonal do período que vem" tem que sobreviver:
    // a janela anda junto, mantendo ~12 meses de defasagem — e não vira "os últimos 3 meses".
    $result = shift(
        ['start_date' => '2025-01-01', 'end_date' => '2025-03-31', 'table_type' => 'monthly_summaries'],
        configuredAt: '2026-01-10',
        now: '2026-04-10',
    );

    expect($result['end_date'])->toStartWith('2025-06')
        ->and($result['start_date'])->toStartWith('2025-04');
});

test('janela recente continua recente', function (): void {
    // Defasagem curta (gerou em 10/jan com dados até 31/dez): reprocessar em abril dá "até março".
    $result = shift(
        ['start_date' => '2025-10-01', 'end_date' => '2025-12-31', 'table_type' => 'monthly_summaries'],
        configuredAt: '2026-01-10',
        now: '2026-04-10',
    );

    expect($result['end_date'])->toBe('2026-03-31')
        ->and($result['start_date'])->toBe('2026-01-01');
});

test('com monthly_summaries a janela termina num mês FECHADO', function (): void {
    // Terminar no mês em curso significaria vendas parciais: as classes ABC despencariam para
    // todo mundo com dados incompletos.
    $result = shift(
        ['start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'table_type' => 'monthly_summaries'],
        configuredAt: '2026-02-01',
        now: '2026-07-14',
    );

    $end = CarbonImmutable::parse($result['end_date']);

    expect($end->lessThan(CarbonImmutable::parse('2026-07-01')))->toBeTrue()
        ->and($end->toDateString())->toBe($end->endOfMonth()->toDateString());
});

test('preserva o comprimento da janela', function (): void {
    $result = shift(
        ['start_date' => '2025-01-01', 'end_date' => '2025-01-31', 'table_type' => 'sales'],
        configuredAt: '2025-02-01',
        now: '2026-07-14',
    );

    $days = CarbonImmutable::parse($result['start_date'])->diffInDays(CarbonImmutable::parse($result['end_date']));

    expect((int) $days)->toBe(30);
});

test('sem janela definida, nada é deslocado', function (): void {
    $config = ['strategy' => 'abc', 'start_date' => null, 'end_date' => null];

    expect(shift($config, '2026-01-01', '2026-07-14'))->toBe($config);
});
