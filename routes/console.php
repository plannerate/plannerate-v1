<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('integrations:dispatch-daily')->dailyAt('01:30');
Schedule::command('integrations:dispatch-nightly-maintenance')->dailyAt('03:30');
