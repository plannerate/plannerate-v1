<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class EnforceSingleSession
{
    /**
     * Derruba todas as sessões anteriores do usuário ao fazer novo login,
     * quando o tenant tem a funcionalidade de login único ativada.
     */
    public function handle(Login $event): void
    {
        if (! app()->bound('current.tenant')) {
            return;
        }

        $tenant = app('current.tenant');
        $enabled = (bool) data_get($tenant?->settings, 'features.single_session', false);

        if (! $enabled) {
            return;
        }

        $landlordConnection = config('raptor.database.landlord_connection_name', 'landlord');

        DB::connection($landlordConnection)
            ->table('sessions')
            ->where('user_id', $event->user->id)
            ->where('id', '!=', session()->getId())
            ->delete();
    }
}
