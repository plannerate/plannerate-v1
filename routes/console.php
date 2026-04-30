<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('integrations:dispatch-daily')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->name('integrations-dispatch-daily');

Schedule::command('integrations:dispatch-nightly-maintenance')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->name('integrations-nightly-maintenance');

Schedule::command('logs:clean-old --days=5')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->name('logs-clean-old');

// Schedule::call(function (): bool {
//     Log::info('Scheduler test log executed.'. request()->getHost());

//     return true;
// })
//     ->everyMinute()
//     ->environments(['local'])
//     ->name('scheduler-test-log');
