<?php

use App\Support\Database\DatabaseConnectionConfigBuilder;
use App\Support\Database\DatabaseCreator;
use Illuminate\Database\Connection;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

test('tenant and landlord connections resolve as pgsql from environment', function (): void {
    $config = app(DatabaseConnectionConfigBuilder::class)->build('pgsql', [
        'host' => 'postgres',
        'port' => '5432',
        'database' => 'landlord',
        'username' => 'sail',
        'password' => 'password',
    ]);

    expect($config)
        ->toMatchArray([
            'driver' => 'pgsql',
            'host' => 'postgres',
            'port' => '5432',
            'database' => 'landlord',
        ]);
});

test('database creator uses postgres syntax only when database does not exist', function (): void {
    $connection = Mockery::mock(Connection::class);
    $catalogQuery = Mockery::mock();

    $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
    $connection->shouldReceive('table')->once()->with('pg_database')->andReturn($catalogQuery);

    $catalogQuery->shouldReceive('where')->once()->with('datname', 'tenant_demo')->andReturnSelf();
    $catalogQuery->shouldReceive('exists')->once()->andReturn(false);

    $connection->shouldReceive('statement')->once()->with('CREATE DATABASE "tenant_demo"');

    app(DatabaseCreator::class)->ensureExists($connection, 'tenant_demo');
});

test('database creator skips postgres create when database already exists', function (): void {
    $connection = Mockery::mock(Connection::class);
    $catalogQuery = Mockery::mock();

    $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
    $connection->shouldReceive('table')->once()->with('pg_database')->andReturn($catalogQuery);

    $catalogQuery->shouldReceive('where')->once()->with('datname', 'tenant_demo')->andReturnSelf();
    $catalogQuery->shouldReceive('exists')->once()->andReturn(true);

    $connection->shouldNotReceive('statement');

    app(DatabaseCreator::class)->ensureExists($connection, 'tenant_demo');
});
