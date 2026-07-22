<?php

use Callcocam\LaravelIntegrations\Jobs\Cleanup\RestoreSoldProductsJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const RESTORE_TENANT_ID = '01jym02qk8n1cwdq2hd5drpgsz';

beforeEach(function (): void {
    config([
        'database.connections.landlord' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('landlord');
    DB::purge('tenant');

    Schema::connection('landlord')->create('tenants', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('database')->unique();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    DB::connection('landlord')->table('tenants')->insert([
        'id' => RESTORE_TENANT_ID,
        'name' => 'Albert',
        'slug' => 'albert',
        'database' => 'tenant_albert',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->string('name')->nullable();
        $table->string('ean')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    // Índice único parcial idêntico ao de produção: só vale para ativos.
    DB::connection('tenant')->statement(
        'CREATE UNIQUE INDEX products_tenant_id_ean_unique ON products (tenant_id, ean) WHERE deleted_at IS NULL'
    );
});

function makeRestoreProduct(string $id, string $ean, ?string $deletedAt): void
{
    DB::connection('tenant')->table('products')->insert([
        'id' => $id,
        'tenant_id' => RESTORE_TENANT_ID,
        'name' => "prod {$id}",
        'ean' => $ean,
        'status' => $deletedAt === null ? 'draft' : 'published',
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => $deletedAt,
    ]);
}

it('restaura os produtos sem colisão e pula os que já têm EAN ativo', function (): void {
    // Deletado com EAN cujo slot ativo já está ocupado por um draft ativo → deve pular.
    makeRestoreProduct('01DELETADOCONFLITANTE00000001', '7891080132872', '2025-08-28 14:58:16');
    makeRestoreProduct('01ATIVODRAFTOCUPANDOSLOT00001', '7891080132872', null);

    // Deletado sem conflito → deve restaurar.
    makeRestoreProduct('01DELETADOSEMCONFLITO00000002', '7891000000002', '2025-08-28 14:58:16');

    $job = new RestoreSoldProductsJob(
        RESTORE_TENANT_ID,
        ['01DELETADOCONFLITANTE00000001', '01DELETADOSEMCONFLITO00000002'],
        'tenant',
        executeInTenantContext: false,
    );

    $job->handle();

    // O conflitante continua deletado (não quebrou nada ativo).
    expect(DB::connection('tenant')->table('products')->where('id', '01DELETADOCONFLITANTE00000001')->value('deleted_at'))
        ->not->toBeNull();

    // O sem conflito foi restaurado.
    expect(DB::connection('tenant')->table('products')->where('id', '01DELETADOSEMCONFLITO00000002')->value('deleted_at'))
        ->toBeNull();

    // O draft ativo permanece intocado.
    expect(DB::connection('tenant')->table('products')->where('id', '01ATIVODRAFTOCUPANDOSLOT00001')->value('deleted_at'))
        ->toBeNull();
});

it('restaura apenas um quando o conjunto tem EAN duplicado entre deletados', function (): void {
    makeRestoreProduct('01DELETADODUPLICADOEAN0000001', '7891000000003', '2025-08-28 14:58:16');
    makeRestoreProduct('01DELETADODUPLICADOEAN0000002', '7891000000003', '2025-08-28 14:58:16');

    $job = new RestoreSoldProductsJob(
        RESTORE_TENANT_ID,
        ['01DELETADODUPLICADOEAN0000001', '01DELETADODUPLICADOEAN0000002'],
        'tenant',
        executeInTenantContext: false,
    );

    $job->handle();

    $restored = DB::connection('tenant')->table('products')
        ->whereIn('id', ['01DELETADODUPLICADOEAN0000001', '01DELETADODUPLICADOEAN0000002'])
        ->whereNull('deleted_at')
        ->count();

    // Exatamente um restaurado — o outro permanece deletado, sem violar o índice.
    expect($restored)->toBe(1);
});
