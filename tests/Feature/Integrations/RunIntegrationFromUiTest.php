<?php

use App\Jobs\Integrations\RunIntegrationPipelineJob;
use App\Models\IntegrationApi;
use App\Models\IntegrationImportRun;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/*
 * Botões "Executar importação" e "Executar pós-importação" na tela da integração
 * do tenant — para não precisar esperar o agendamento das 06:00.
 *
 * Sempre ENFILEIRADO: a descoberta faz chamadas HTTP por loja e o fan-out dura
 * minutos; rodar síncrono estouraria o request.
 */

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Gate::before(fn (): bool => true);
    // Sem usuário autenticado o request para no middleware `auth` e redireciona
    // para o login — os casos de "recusa" passariam à toa, sem tocar no controller.
    $this->actingAs(User::factory()->create());
});

function makeRunUiTenant(string $slug, bool $integrationActive = true, bool $apiActive = true): Tenant
{
    $tenant = Tenant::withoutEvents(function () use ($slug): Tenant {
        return Tenant::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'database' => 'tenant_'.$slug,
            'status' => 'active',
        ]);
    });

    $api = IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => ['method' => 'GET', 'paths' => ['products' => ['target_table' => 'products']]],
        'response' => ['items_path' => 'data'],
        'is_active' => $apiActive,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.runui.test']],
        'is_active' => $integrationActive,
    ]);

    return $tenant;
}

/** @return array<int, RunIntegrationPipelineJob> */
function jobsEnfileirados(): array
{
    $jobs = [];

    Queue::assertPushed(RunIntegrationPipelineJob::class, function (RunIntegrationPipelineJob $job) use (&$jobs): bool {
        $jobs[] = $job;

        return true;
    });

    return $jobs;
}

test('enfileira a importação apenas para o tenant da tela', function (): void {
    Queue::fake();
    $tenant = makeRunUiTenant('run-ui-import');

    $this->post(route('landlord.tenants.integration.run-import', $tenant))
        ->assertRedirect();

    $jobs = jobsEnfileirados();

    expect($jobs)->toHaveCount(1)
        ->and($jobs[0]->step)->toBe(RunIntegrationPipelineJob::STEP_IMPORT)
        // Sem o tenant o botão dispararia a importação de TODOS os tenants ativos.
        ->and($jobs[0]->tenantId)->toBe((string) $tenant->id);
});

test('enfileira a pós-importação com espera que cabe no timeout do worker', function (): void {
    Queue::fake();
    $tenant = makeRunUiTenant('run-ui-post');

    $this->post(route('landlord.tenants.integration.run-post-import', $tenant))
        ->assertRedirect();

    $job = jobsEnfileirados()[0];

    expect($job->step)->toBe(RunIntegrationPipelineJob::STEP_POST_IMPORT)
        ->and($job->tenantId)->toBe((string) $tenant->id)
        // supervisor-maintenance tem timeout de 1860s: espera maior seria morta
        // no meio, e o job precisa caber junto com ela.
        ->and($job->waitMinutes)->toBeLessThan(31)
        ->and($job->timeout)->toBeLessThan(1860);
});

test('o job não é tenant-aware — é disparado do painel landlord', function (): void {
    // queues_are_tenant_aware_by_default = true: sem NotTenantAware o Spatie
    // tenta resolver um tenant que não existe no contexto do landlord e estoura
    // CurrentTenantCouldNotBeDeterminedInTenantAwareJob ao processar.
    expect(new RunIntegrationPipelineJob(RunIntegrationPipelineJob::STEP_IMPORT, 'tenant-ulid'))
        ->toBeInstanceOf(NotTenantAware::class);
});

test('recusa etapa desconhecida — o payload da fila não executa comando arbitrário', function (): void {
    expect(fn () => new RunIntegrationPipelineJob('rm-rf', 'tenant-ulid'))
        ->toThrow(InvalidArgumentException::class);
});

test('recusa quando já há importação em andamento — evita fan-out duplicado', function (): void {
    Queue::fake();
    $tenant = makeRunUiTenant('run-ui-em-andamento');

    IntegrationImportRun::startRun([
        'tenant_id' => (string) $tenant->id,
        'integration_id' => (string) $tenant->integration->id,
        'path_key' => 'products',
        'store_id' => null,
        'mode' => 'cursor',
        'reference_date' => now()->toDateString(),
        'expected_units' => 1,
    ]);

    $this->post(route('landlord.tenants.integration.run-import', $tenant))
        ->assertRedirect();

    Queue::assertNotPushed(RunIntegrationPipelineJob::class);
});

test('recusa quando a integração está inativa', function (): void {
    Queue::fake();
    $tenant = makeRunUiTenant('run-ui-inativa', integrationActive: false);

    $this->post(route('landlord.tenants.integration.run-import', $tenant))->assertRedirect();
    $this->post(route('landlord.tenants.integration.run-post-import', $tenant))->assertRedirect();

    Queue::assertNotPushed(RunIntegrationPipelineJob::class);
});

test('recusa quando o blueprint está inativo', function (): void {
    Queue::fake();
    $tenant = makeRunUiTenant('run-ui-api-inativa', apiActive: false);

    $this->post(route('landlord.tenants.integration.run-import', $tenant))->assertRedirect();

    Queue::assertNotPushed(RunIntegrationPipelineJob::class);
});

test('recusa quando o tenant não tem integração configurada', function (): void {
    Queue::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'SEM INTEGRACAO',
        'slug' => 'run-ui-sem',
        'database' => 'tenant_run_ui_sem',
        'status' => 'active',
    ]));

    $this->post(route('landlord.tenants.integration.run-import', $tenant))->assertRedirect();

    Queue::assertNotPushed(RunIntegrationPipelineJob::class);
});
