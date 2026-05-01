<?php

use App\Models\Provider;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Sysmo\SysmoEndpoints;
use App\Services\Integrations\Sysmo\SysmoProvidersIntegrationService;
use App\Services\Integrations\Sysmo\SysmoProvidersResponseMapper;
use Illuminate\Support\Facades\DB;

test('persist mapped providers upserts to providers table', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();

    $service = new SysmoProvidersIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoProvidersResponseMapper,
        new DeterministicIdGenerator,
    );

    $service->persistMappedProviders($tenantId, [
        [
            'code' => '500298',
            'name' => 'GLOBAL DISTRIBUICAO DE BENS DE CONSUMO LTDA',
            'cnpj' => '89237911000140',
            'description' => 'LOJAS HERVAL',
            'address_street' => 'RODOVIA BR 116  7350',
            'address_district' => 'PORTAL DA SERRA',
            'address_city' => 'DOIS IRMAOS',
            'address_state' => 'RS',
            'address_zip_code' => '93950000',
            'address_complement' => null,
        ],
    ]);

    $provider = DB::table('providers')
        ->where('tenant_id', $tenantId)
        ->where('code', '500298')
        ->first();

    expect($provider)->not->toBeNull()
        ->and((string) $provider?->id)->toStartWith('F1')
        ->and($provider?->name)->toBe('GLOBAL DISTRIBUICAO DE BENS DE CONSUMO LTDA')
        ->and($provider?->cnpj)->toBe('89237911000140')
        ->and($provider?->description)->toBe('LOJAS HERVAL');

    $address = DB::table('addresses')
        ->where('addressable_type', Provider::class)
        ->where('addressable_id', $provider?->id)
        ->first();

    expect($address)->not->toBeNull()
        ->and((string) $address?->id)->toStartWith('FA')
        ->and($address?->street)->toBe('RODOVIA BR 116  7350')
        ->and($address?->district)->toBe('PORTAL DA SERRA')
        ->and($address?->city)->toBe('DOIS IRMAOS')
        ->and($address?->state)->toBe('RS')
        ->and($address?->zip_code)->toBe('93950000')
        ->and($address?->country)->toBe('Brasil')
        ->and((bool) $address?->is_default)->toBeTrue();
});

test('persist mapped providers skips items without code', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();

    $service = new SysmoProvidersIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoProvidersResponseMapper,
        new DeterministicIdGenerator,
    );

    $service->persistMappedProviders($tenantId, [
        [
            'code' => null,
            'name' => 'SEM CODIGO',
            'cnpj' => '00000000000000',
            'description' => null,
            'address_street' => null,
            'address_district' => null,
            'address_city' => null,
            'address_state' => null,
            'address_zip_code' => null,
            'address_complement' => null,
        ],
    ]);

    expect(DB::table('providers')->where('tenant_id', $tenantId)->count())->toBe(0);
});

test('persist mapped providers updates existing provider on re-sync', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();

    $service = new SysmoProvidersIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoProvidersResponseMapper,
        new DeterministicIdGenerator,
    );

    $item = [
        'code' => '999',
        'name' => 'NOME ORIGINAL',
        'cnpj' => '11222333000181',
        'description' => 'FANTASIA ORIGINAL',
        'address_street' => 'RUA A',
        'address_district' => 'BAIRRO A',
        'address_city' => 'CIDADE A',
        'address_state' => 'SP',
        'address_zip_code' => '01310100',
        'address_complement' => null,
    ];

    $service->persistMappedProviders($tenantId, [$item]);

    $updatedItem = array_merge($item, ['name' => 'NOME ATUALIZADO', 'address_city' => 'CIDADE B']);
    $service->persistMappedProviders($tenantId, [$updatedItem]);

    expect(DB::table('providers')->where('tenant_id', $tenantId)->where('code', '999')->count())->toBe(1);

    $provider = DB::table('providers')->where('tenant_id', $tenantId)->where('code', '999')->first();
    expect($provider?->name)->toBe('NOME ATUALIZADO');

    $address = DB::table('addresses')
        ->where('addressable_type', Provider::class)
        ->where('addressable_id', $provider?->id)
        ->first();
    expect($address?->city)->toBe('CIDADE B');
});

test('persist mapped providers chunks upserts correctly', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();
    $insertStatements = 0;

    DB::listen(function ($query) use (&$insertStatements): void {
        $sql = mb_strtolower($query->sql);

        if (str_contains($sql, 'insert into "providers"') || str_contains($sql, 'insert into `providers`')) {
            $insertStatements++;
        }
    });

    $service = new SysmoProvidersIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoProvidersResponseMapper,
        new DeterministicIdGenerator,
    );

    $items = [];
    for ($index = 1; $index <= 501; $index++) {
        $items[] = [
            'code' => (string) (10000 + $index),
            'name' => 'Fornecedor '.$index,
            'cnpj' => null,
            'description' => null,
            'address_street' => null,
            'address_district' => null,
            'address_city' => null,
            'address_state' => null,
            'address_zip_code' => null,
            'address_complement' => null,
        ];
    }

    $service->persistMappedProviders($tenantId, $items);

    expect($insertStatements)->toBe(2)
        ->and(DB::table('providers')->where('tenant_id', $tenantId)->count())->toBe(501);
});
