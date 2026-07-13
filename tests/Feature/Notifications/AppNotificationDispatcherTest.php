<?php

use App\Events\TenantNotificationBroadcast;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\AppNotificationDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/**
 * Regressão: o broadcast saía com `id: null`.
 *
 * notifyNow() entrega um CLONE da notificação ao canal e é nele que o Laravel gera o
 * id — o objeto do chamador continuava com id null, e o payload transmitido também.
 * No cliente, o item recém-chegado ficava sem id e a montagem da URL de download
 * (`/notifications/{id}/download`) estourava, derrubando o dropdown inteiro.
 */

/**
 * Coloca um tenant "corrente": o User resolve a conexão dinamicamente (tenant quando
 * há tenant corrente, landlord quando não há), então sem isso ele iria ao landlord.
 */
function fakeNotificationTenant(string $tenantId): Tenant
{
    $defaultConnection = (string) config('database.default');

    $tenant = new Tenant;
    $tenant->id = $tenantId;
    $tenant->database = (string) config("database.connections.{$defaultConnection}.database", 'testing');

    Tenant::forgetCurrent();
    $tenant->makeCurrent();

    return $tenant;
}

function buildNotificationsSchema(): void
{
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('users', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::connection('tenant')->create('notifications', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->char('tenant_id', 26)->nullable();
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
}

function makeNotifiableUser(string $id): User
{
    $user = new User;
    $user->forceFill(['id' => $id, 'name' => 'Usuário', 'email' => 'u@example.com'])->save();

    return $user;
}

beforeEach(function (): void {
    // Ordem importa: makeCurrent() reconecta a conexão `tenant` (:memory:), o que
    // descartaria um schema criado antes dele.
    fakeNotificationTenant('01jtenantdispatch00000000');
    buildNotificationsSchema();
    Event::fake([TenantNotificationBroadcast::class]);
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('o broadcast leva o mesmo id da notificação persistida', function (): void {
    $user = makeNotifiableUser('01juserdispatch0000000000');

    app(AppNotificationDispatcher::class)->send(
        $user,
        new AppNotification(
            title: 'Gôndola gerada',
            message: '10 produto(s) posicionado(s).',
            type: 'success',
        ),
        context: 'TestJob',
    );

    $persistedId = $user->notifications()->sole()->id;

    expect($persistedId)->not->toBeNull();

    Event::assertDispatched(
        TenantNotificationBroadcast::class,
        // Sem id, o cliente não consegue baixar/marcar lida/excluir a notificação
        // recém-chegada — e a renderização do painel quebra.
        fn (TenantNotificationBroadcast $event): bool => $event->payload['id'] === $persistedId
            && $event->userId === (string) $user->getKey(),
    );
});

test('o payload transmitido carrega os dados que o sino exibe', function (): void {
    $user = makeNotifiableUser('01juserdispatchpay0000000');

    app(AppNotificationDispatcher::class)->send(
        $user,
        new AppNotification(
            title: 'Relatório pronto',
            message: 'Disponível para download.',
            type: 'success',
            downloadUrl: 'reports/tenant/relatorio.xlsx',
            downloadName: 'relatorio.xlsx',
        ),
        context: 'TestJob',
    );

    Event::assertDispatched(TenantNotificationBroadcast::class, function (TenantNotificationBroadcast $event): bool {
        return $event->payload['title'] === 'Relatório pronto'
            && $event->payload['notification_type'] === 'success'
            && $event->payload['download_name'] === 'relatorio.xlsx'
            && $event->payload['read_at'] === null
            && $event->payload['type'] === AppNotification::class;
    });
});
