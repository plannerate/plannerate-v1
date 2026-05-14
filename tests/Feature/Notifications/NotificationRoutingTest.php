<?php

use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Jobs\NotTenantAware;

test('broadcast auth route is registered for landlord and tenant domains', function (): void {
    $landlordHost = (string) config('app.landlord_domain');
    $tenantHost = 'acme.'.$landlordHost;

    $landlordRoute = Route::getRoutes()->match(Request::create(
        'http://'.$landlordHost.'/broadcasting/auth',
        'POST',
    ));
    $tenantRoute = Route::getRoutes()->match(Request::create(
        'http://'.$tenantHost.'/broadcasting/auth',
        'POST',
    ));

    expect($landlordRoute->uri())->toBe('broadcasting/auth')
        ->and($tenantRoute->uri())->toBe('broadcasting/auth');
});

test('app notification broadcast payload includes tenant context', function (): void {
    $notification = new AppNotification(
        title: 'Importação concluída',
        message: 'Categorias atualizadas.',
        type: 'success',
        tenantId: '01HXTESTTENANT000000000000',
    );

    expect($notification->toArray(new stdClass))
        ->toMatchArray([
            'title' => 'Importação concluída',
            'message' => 'Categorias atualizadas.',
            'notification_type' => 'success',
            'tenant_id' => '01HXTESTTENANT000000000000',
        ]);
});

test('app notifications and their broadcast events remain tenant aware', function (): void {
    expect(new AppNotification(
        title: 'Importação concluída',
        message: 'Categorias atualizadas.',
    ))->not->toBeInstanceOf(NotTenantAware::class)
        ->and(config('multitenancy.not_tenant_aware_jobs'))->not->toContain(
            BroadcastNotificationCreated::class,
        );
});
