<?php

namespace App\Providers;

use App\Events\Tenant\ProductImageProcessed;
use App\Events\Tenant\TenantIsolationCheckEvent;
use App\Listeners\Landlord\CreateTenantIsolationCheckNotification;
use App\Listeners\Tenant\CreateTenantIsolationTenantNotification;
use App\Listeners\Tenant\SaveEanReferenceOnProductImageProcessed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
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
