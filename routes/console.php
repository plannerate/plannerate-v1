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

// Limpa órfãos de storage/app/private/imports e a quarentena imports/failed
// antes do integration:run das 06:00.
Schedule::command('imports:prune')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('imports-prune');

Schedule::command('integration:run')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('integration:run');

// Pipeline pós-importação. O gap de 1h30 após integration:run é só o chute
// inicial: o comando espera as filas imports-fetch/imports-process esvaziarem
// (até --wait-minutes) antes de rodar, para não agir sobre import parcial.
// Ordem: sync:link-sales → sync:cleanup → sync:products-from-ean-references
Schedule::command('sync:post-import')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('sync-post-import');

// Health check do import: roda após o pós-import (que já esperou as filas
// esvaziarem). Read-only; loga Log::warning se houver sinal de alerta
// (import atrasado, backlog, quarentena) para monitoramento passivo.
Schedule::command('integration:health')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('integration-health');

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

// ANTES do trigger das 04:30, de propósito: expirar as propostas abandonadas destrava as gôndolas
// que estavam presas por elas, e essas gôndolas já entram na análise da mesma madrugada.
Schedule::command('planograms:prune-reoptimization-proposals')
    ->dailyAt('04:20')
    ->withoutOverlapping()
    ->name('planograms-prune-reoptimization-proposals');

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
