<?php

namespace App\Providers;

use App\Events\Tenant\ProductImageProcessed;
use App\Listeners\Landlord\CreateTenantIsolationCheckNotification;
use App\Listeners\Tenant\CreateTenantIsolationTenantNotification;
use App\Listeners\Tenant\SaveEanReferenceOnProductImageProcessed;
use Callcocam\LaravelIntegrations\Events\TenantIsolationCheckEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Desativa auto-discovery de listeners.
     *
     * O Laravel 13 tem $shouldDiscoverEvents = true por padrão, o que faz listeners
     * serem registrados duas vezes: uma pelo $listen e outra pela varredura automática
     * do diretório app/Listeners. Desativamos aqui e usamos o $listen como fonte única.
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TenantIsolationCheckEvent::class => [
            CreateTenantIsolationTenantNotification::class,
            CreateTenantIsolationCheckNotification::class,
        ],
        ProductImageProcessed::class => [
            SaveEanReferenceOnProductImageProcessed::class,
        ],
    ];
}
