<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Safety Guardrails
|--------------------------------------------------------------------------
|
| Abort early if tests are started in a non-testing environment or with
| non-test database names. This prevents accidental destructive test runs.
|
*/

$assertSafeTestingDatabases = static function (): void {
    if (! app()->environment('testing')) {
        throw new RuntimeException('Unsafe test execution blocked: APP_ENV must be testing.');
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

    $databaseNames = [
        'default' => config('database.connections.'.config('database.default').'.database'),
        'landlord' => config('database.connections.landlord.database'),
        'tenant' => config('database.connections.tenant.database'),
    ];

    foreach ($databaseNames as $connectionName => $databaseName) {
        if (! is_string($databaseName) && $databaseName !== null) {
            throw new RuntimeException(sprintf(
                'Unsafe test execution blocked: invalid database value for [%s].',
                $connectionName,
            ));
        }

        if (! $isSafeDatabaseName($databaseName)) {
            throw new RuntimeException(sprintf(
                'Unsafe test execution blocked: connection [%s] points to non-test database [%s].',
                $connectionName,
                (string) $databaseName,
            ));
        }
    }
};

$assertSafeTestingDatabases();

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
