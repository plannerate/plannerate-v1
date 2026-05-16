<?php

use App\Providers\AppServiceProvider;
use App\Providers\AutoPlanogramServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AutoPlanogramServiceProvider::class,
    EventServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
