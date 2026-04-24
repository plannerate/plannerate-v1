<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns;

trait HasWorkflowToggle
{
    protected function isWorkflowEnabled(): bool
    {
        $tenant = app()->bound('current.tenant') ? app('current.tenant') : null;

        return (bool) data_get($tenant?->settings, 'features.use_workflow', false);
    }
}
