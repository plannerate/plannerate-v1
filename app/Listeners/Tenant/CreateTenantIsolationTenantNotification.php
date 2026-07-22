<?php

namespace App\Listeners\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelIntegrations\Events\TenantIsolationCheckEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantIsolationTenantNotification
{
    public function handle(TenantIsolationCheckEvent $event): void
    {
        $tenant = Tenant::query()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->execute(function () use ($event): void {
            $users = User::query()->get();

            foreach ($users as $user) {
                $title = $event->status === 'ok'
                    ? 'Isolamento de Tenant Validado'
                    : 'Aviso de Isolamento de Tenant';

                $message = $event->status === 'ok'
                    ? sprintf('Seu tenant %s passou na validacao de isolamento.', $event->tenantSlug)
                    : sprintf('Tenant esperado %s mas obteve %s.', $event->tenantId, $event->currentTenantId);

                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\AppNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => (string) $user->getKey(),
                    'tenant_id' => (string) $event->tenantId,
                    'data' => json_encode([
                        'title' => $title,
                        'message' => $message,
                        'notification_type' => $event->status === 'ok' ? 'success' : 'warning',
                        'action_url' => null,
                        'download_url' => null,
                        'download_name' => null,
                    ], JSON_UNESCAPED_UNICODE),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
