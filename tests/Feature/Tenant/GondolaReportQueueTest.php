<?php

use App\Events\TenantNotificationBroadcast;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController;
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateGondolaReportJob;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Queue;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Testes de enfileiramento dos relatórios de gôndola.
 *
 * Não tocam no banco (usam models em memória para auth()/Tenant::current() e
 * Queue::fake()), evitando o harness tenant :memory: — pré-existentemente frágil
 * neste ambiente — e mantendo o foco na nova lógica de dispatch do controller.
 */

/**
 * Coloca um tenant "corrente" em memória, sem persistir.
 */
function fakeCurrentTenant(string $tenantId): Tenant
{
    $defaultConnection = (string) config('database.default');

    $tenant = new Tenant;
    $tenant->id = $tenantId;
    // makeCurrent() dispara as tasks do Spatie (troca de conexão), que exigem
    // um database não-nulo. Reaproveita o database da conexão default de teste.
    $tenant->database = (string) config("database.connections.{$defaultConnection}.database", 'testing');

    Tenant::forgetCurrent();
    $tenant->makeCurrent();

    return $tenant;
}

/**
 * Autentica um usuário em memória (sem persistir), suficiente para auth()->id().
 */
function actingAsInMemoryUser(string $userId): User
{
    $user = new User;
    $user->id = $userId;
    test()->actingAs($user);

    return $user;
}

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('generateExcelReport enfileira o job com o contexto de tenant/usuário correto', function (): void {
    Queue::fake();

    $tenant = fakeCurrentTenant('01jtenantreportqueue000000');
    $user = actingAsInMemoryUser('01juserreportqueue00000000');

    $controller = app(GondolaReportController::class);
    $gondolaId = '01jgondolareportqueue00000';

    $response = $controller->generateExcelReport($gondolaId);

    // Mutação consumida via router.post → redirect (back).
    expect($response)->toBeInstanceOf(RedirectResponse::class);

    Queue::assertPushed(GenerateGondolaReportJob::class, function (GenerateGondolaReportJob $job) use ($gondolaId, $user, $tenant): bool {
        return $job->gondolaId === $gondolaId
            && $job->format === 'excel'
            && $job->userId === (string) $user->id
            && $job->tenantId === (string) $tenant->id;
    });
});

test('cada endpoint de relatório enfileira o job com o formato correspondente', function (): void {
    Queue::fake();

    fakeCurrentTenant('01jtenantreportformats0000');
    actingAsInMemoryUser('01juserreportformats000000');

    $controller = app(GondolaReportController::class);
    $gondolaId = '01jgondolareportformats000';

    $controller->generatePdfReport($gondolaId);
    $controller->generateCompraReport($gondolaId);
    $controller->generateDimensaoReport($gondolaId);
    $controller->generateImageReport($gondolaId);

    foreach (['pdf', 'compra', 'dimensao', 'image'] as $format) {
        Queue::assertPushed(GenerateGondolaReportJob::class, fn (GenerateGondolaReportJob $job): bool => $job->format === $format
            && $job->gondolaId === $gondolaId);
    }
});

test('o job de relatório roda na fila default, sem retry e é TenantAware', function (): void {
    $job = new GenerateGondolaReportJob('01jgondola', 'excel', '01juser', '01jtenant');

    expect($job->queue)->toBe('default');
    expect($job->tries)->toBe(1);
    expect($job->timeout)->toBe(600);
    expect($job)->toBeInstanceOf(TenantAware::class);
});

test('o evento de broadcast é síncrono, no canal do usuário e no evento de notificação do Laravel', function (): void {
    $payload = ['id' => '01jnotif', 'title' => 'Relatório pronto', 'tenant_id' => '01jtenant'];
    $event = new TenantNotificationBroadcast('01juser', $payload);

    // ShouldBroadcastNow → roda em processo (sem fila, sem restaurar models).
    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    expect($event->broadcastOn()->name)->toBe('private-App.Models.User.01juser');
    // Mesmo nome do evento que o Echo escuta (useEchoNotification).
    expect($event->broadcastAs())->toBe('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated');
    expect($event->broadcastWith())->toBe($payload);
});
