<?php

$vendorPlannerateConfig = base_path('vendor/callcocam/laravel-raptor-plannerate/config/plannerate.php');

if (file_exists($vendorPlannerateConfig)) {
    return require $vendorPlannerateConfig;
}

return [];
