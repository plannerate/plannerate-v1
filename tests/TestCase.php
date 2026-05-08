<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
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

        $isSafeDatabaseName = static function (?string $databaseName): bool {
            if ($databaseName === null || $databaseName === '') {
                return true;
            }

            $normalized = strtolower($databaseName);

            return $normalized === ':memory:'
                || str_contains($normalized, 'test')
                || str_contains($normalized, 'testing')
                || str_contains($normalized, 'sqlite');
        };

        $defaultConnection = (string) config('database.default', '');
        $databaseNames = [
            'default' => (string) config("database.connections.{$defaultConnection}.database", ''),
            'landlord' => (string) config('database.connections.landlord.database', ''),
            'tenant' => (string) config('database.connections.tenant.database', ''),
        ];

        foreach ($databaseNames as $connectionName => $databaseName) {
            if (! $isSafeDatabaseName($databaseName)) {
                throw new \RuntimeException(sprintf(
                    'Unsafe test execution blocked: connection [%s] points to non-test database [%s].',
                    $connectionName,
                    $databaseName,
                ));
            }
        }
    }
}
