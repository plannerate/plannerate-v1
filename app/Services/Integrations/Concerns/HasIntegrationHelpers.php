<?php

namespace App\Services\Integrations\Concerns;

use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;

trait HasIntegrationHelpers
{
    private function rowIsEnabled(array $row): bool
    {
        if (! array_key_exists('enabled', $row)) {
            return true;
        }

        $enabled = $row['enabled'];
        if (is_bool($enabled)) {
            return $enabled;
        }

        if (is_string($enabled) || is_int($enabled)) {
            return filter_var($enabled, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        }

        return true;
    }

    private function validTableName(string $table): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $table) === 1;
    }

    private function resolveConfig(ResolvedIntegrationConfig|TenantIntegration $config): ResolvedIntegrationConfig
    {
        if ($config instanceof ResolvedIntegrationConfig) {
            return $config;
        }

        return (isset($this->configResolver) && $this->configResolver instanceof ResolvedIntegrationConfigResolver
            ? $this->configResolver
            : app(ResolvedIntegrationConfigResolver::class)
        )->resolve($config);
    }
}
