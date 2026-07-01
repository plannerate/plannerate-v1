<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;

$tenant = Tenant::where('slug', 'alberti')->first() ?? Tenant::first();
$tenant->makeCurrent();
echo "TENANT: {$tenant->name}\n";
$g = Gondola::query()->latest()->first();
echo 'GONDOLA: '.($g?->id ?? 'none')."\n";
