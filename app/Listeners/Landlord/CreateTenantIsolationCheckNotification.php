<?php

namespace App\Listeners\Landlord;

use App\Models\User;
use Callcocam\LaravelIntegrations\Events\TenantIsolationCheckEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantIsolationCheckNotification
{
    public function handle(TenantIsolationCheckEvent $event): void
    {
        if ($event->status === 'ok') {
            return;
        }

        $users = User::on('landlord')->get();

        foreach ($users as $user) {
            $title = $event->status === 'ok'
                ? 'Isolamento de Tenant Validado ✓'
                : 'Aviso: Mismatch no Isolamento de Tenant';

            $message = $event->status === 'ok'
                ? sprintf('Tenant %s passou na validacao de isolamento', $event->tenantSlug)
                : sprintf('Tenant esperado %s mas obteve %s', $event->tenantId, $event->currentTenantId);

            DB::connection('landlord')->table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\AppNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => (string) $user->getKey(),
                'tenant_id' => null,
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
    }
}
