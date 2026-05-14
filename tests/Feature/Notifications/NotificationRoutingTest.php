<?php

use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
