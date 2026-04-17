<?php

use App\Models\Client;
use App\Services\ClientMigrationService;
use Callcocam\LaravelRaptor\Services\TenantDatabaseManager;
use Illuminate\Contracts\Config\Repository;
use Mockery;

it('runs client migrations on dedicated database and restores default connection', function () {
    $config = mock(Repository::class);
    $config->shouldReceive('get')->with('flow.client_migrations_path')->andReturn(null);

    $manager = mock(TenantDatabaseManager::class);
    $manager->shouldReceive('getDefaultDatabaseName')
        ->andReturn('central_db');
    $manager->shouldReceive('ensureDatabaseAndRunMigrations')
        ->once()
        ->with('client_dedicated_db', Mockery::on(function (array $paths): bool {
            return in_array('database/migrations/clients', $paths, true)
                && count($paths) >= 1;
        }), null);
    $manager->shouldReceive('setupConnection')
        ->once()
        ->with('central_db');

    $client = new class extends Client
    {
        public function __construct()
        {
            $this->database = 'client_dedicated_db';
            $this->id = 'abc-123';
        }
    };

    $service = new ClientMigrationService($manager, $config);
    $service->runClientMigrations($client);
});

it('runs client migrations on default database when client has no dedicated database', function () {
    $config = mock(Repository::class);
    $config->shouldReceive('get')->with('flow.client_migrations_path')->andReturn(null);

    $manager = mock(TenantDatabaseManager::class);
    $manager->shouldReceive('getDefaultDatabaseName')
        ->andReturn('central_db', 'central_db');
    $manager->shouldReceive('ensureDatabaseAndRunMigrations')
        ->once()
        ->with('central_db', Mockery::on(function (array $paths): bool {
            return in_array('database/migrations/clients', $paths, true)
                && count($paths) >= 1;
        }), null);
    $manager->shouldReceive('setupConnection')
        ->once()
        ->with('central_db');

    $client = new class extends Client
    {
        public function __construct()
        {
            $this->database = null;
            $this->id = 'abc-456';
        }
    };

    $service = new ClientMigrationService($manager, $config);
    $service->runClientMigrations($client);
});

it('does nothing when database name is empty', function () {
    $config = mock(Repository::class);
    $config->shouldReceive('get')->with('flow.client_migrations_path')->andReturn(null);

    $manager = mock(TenantDatabaseManager::class);
    $manager->shouldReceive('getDefaultDatabaseName')
        ->andReturn('');
    $manager->shouldReceive('ensureDatabaseAndRunMigrations')->never();
    $manager->shouldReceive('setupConnection')->never();

    $client = new class extends Client
    {
        public function __construct()
        {
            $this->database = null;
            $this->id = 'abc-789';
        }
    };

    $service = new ClientMigrationService($manager, $config);
    $service->runClientMigrations($client);
});

it('runs only client migrations path when flow is on landlord', function () {
    $config = mock(Repository::class);
    $config->shouldReceive('get')->with('flow.client_migrations_path')->andReturn(null);

    $manager = mock(TenantDatabaseManager::class);
    $manager->shouldReceive('getDefaultDatabaseName')
        ->andReturn('central_db');
    $manager->shouldReceive('ensureDatabaseAndRunMigrations')
        ->once()
        ->with('client_dedicated_db', ['database/migrations/clients'], null);
    $manager->shouldReceive('setupConnection')
        ->once()
        ->with('central_db');

    $client = new class extends Client
    {
        public function __construct()
        {
            $this->database = 'client_dedicated_db';
            $this->id = 'abc-flow';
        }
    };

    $service = new ClientMigrationService($manager, $config);
    $service->runClientMigrations($client);
});
