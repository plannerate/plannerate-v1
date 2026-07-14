<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('logs:archive-and-clear --days=5')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->name('logs-archive-and-clear');

Schedule::command('logs:archive-and-clear --max-size-mb=10')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->name('logs-size-check');

Schedule::command('integration:run')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->name('integration:run');

// Pipeline pós-importação: roda ~1h30 após integration:run para garantir que
// os jobs da fila `imports` já foram processados.
// Ordem: sync:link-sales → sync:cleanup → sync:products-from-ean-references
Schedule::command('sync:post-import')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->name('sync-post-import');

Schedule::command('planograms:trigger-periodic-review')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->name('planograms-trigger-periodic-review');

// Depois da revisão periódica (04:00) e antes do horário comercial: as propostas ficam prontas
// para o gestor encontrar quando abrir o sistema.
Schedule::command('planograms:trigger-reoptimization')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->name('planograms-trigger-reoptimization');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->name('horizon-snapshot');

// Schedule::call(function (): bool {
//     Log::info('Scheduler test log executed.'. request()->getHost());

//     return true;
// })
//     ->everyMinute()
//     ->environments(['local'])
//     ->name('scheduler-test-log');
