<?php

namespace App\Listeners;

use App\Notifications\AppNotification;
use Illuminate\Notifications\Events\NotificationSent;

class UpdateNotificationTenantId
{
    public function handle(NotificationSent $event): void
    {
        // Only process AppNotifications with a tenantId
        if (! ($event->notification instanceof AppNotification) || $event->notification->tenantId === null) {
            return;
        }

        // The notification ID is stored in the notification object
        // We need to update the database record to add the tenant_id
        if ($event->channel === 'database') {
            $event->notifiable
                ->notifications()
                ->where('id', $event->notification->id)
                ->update(['tenant_id' => $event->notification->tenantId]);
        }
    }
}
