<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->assertSafeTestingEnvironmentBeforeApplicationBoot();

        parent::setUp();

        $this->assertSafeTestingDatabases();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private function assertSafeTestingDatabases(): void
    {
        $appEnv = strtolower((string) config('app.env', ''));
        if ($appEnv !== 'testing') {
            throw new \RuntimeException('Unsafe test execution blocked: APP_ENV must be testing.');
        }

        $defaultConnection = (string) config('database.default', '');

        $this->assertSafeTestingDatabaseNames([
            'default' => (string) config("database.connections.{$defaultConnection}.database", ''),
            'landlord' => (string) config('database.connections.landlord.database', ''),
            'tenant' => (string) config('database.connections.tenant.database', ''),
        ], 'application config');
    }

    private function assertSafeTestingEnvironmentBeforeApplicationBoot(): void
    {
        $appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: ''));

        if ($appEnv !== 'testing') {
            throw new \RuntimeException('Unsafe test execution blocked before boot: APP_ENV must be testing.');
        }

        $databaseNames = [
            'default' => (string) ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: ''),
            'landlord' => (string) ($_ENV['DB_LANDLORD_DATABASE'] ?? $_SERVER['DB_LANDLORD_DATABASE'] ?? getenv('DB_LANDLORD_DATABASE') ?: ''),
            'tenant' => (string) ($_ENV['DB_TENANT_DATABASE'] ?? $_SERVER['DB_TENANT_DATABASE'] ?? getenv('DB_TENANT_DATABASE') ?: ''),
        ];

        $this->assertSafeTestingDatabaseNames($databaseNames, 'environment');

        $cachedConfigPath = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cachedConfigPath)) {
            /** @var array<string, mixed> $cachedConfig */
            $cachedConfig = require $cachedConfigPath;

            $cachedAppEnv = strtolower((string) data_get($cachedConfig, 'app.env', ''));
            if ($cachedAppEnv !== 'testing') {
                throw new \RuntimeException(sprintf(
                    'Unsafe test execution blocked from cached config: APP_ENV is [%s], expected [testing]. Run php artisan config:clear before testing.',
                    $cachedAppEnv,
                ));
            }

            $defaultConnection = (string) data_get($cachedConfig, 'database.default', '');

            $this->assertSafeTestingDatabaseNames([
                'cached default' => (string) data_get($cachedConfig, "database.connections.{$defaultConnection}.database", ''),
                'cached landlord' => (string) data_get($cachedConfig, 'database.connections.landlord.database', ''),
                'cached tenant' => (string) data_get($cachedConfig, 'database.connections.tenant.database', ''),
            ], 'cached config');
        }
    }

    /**
     * @param  array<string, string>  $databaseNames
     */
    private function assertSafeTestingDatabaseNames(array $databaseNames, string $source): void
    {
        foreach ($databaseNames as $connectionName => $databaseName) {
            if ($this->isSafeTestingDatabaseName($databaseName)) {
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Unsafe test execution blocked from %s: connection [%s] points to non-test database [%s].',
                $source,
                $connectionName,
                $databaseName,
            ));
        }
    }

    private function isSafeTestingDatabaseName(?string $databaseName): bool
    {
        if ($databaseName === null || $databaseName === '') {
            return true;
        }

        $normalized = strtolower($databaseName);

        if ($normalized === 'landlord' || str_starts_with($normalized, 'tenant_')) {
            return false;
        }

        return $normalized === ':memory:'
            || str_contains($normalized, 'test')
            || str_contains($normalized, 'testing')
            || str_contains($normalized, 'sqlite');
    }
}
