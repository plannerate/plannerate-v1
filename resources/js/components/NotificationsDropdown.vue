<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useConnectionStatus, useEchoNotification } from '@laravel/echo-vue';
import type { ConnectionStatus } from '@laravel/echo-vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';
import {
    destroyAll as destroyAllLandlordRoute,
    destroy as destroyLandlordRoute,
    download as downloadLandlordRoute,
    markAllRead as markAllReadLandlordRoute,
} from '@/actions/App/Http/Controllers/Landlord/NotificationController';
import {
    destroyAll as destroyAllTenantRoute,
    destroy as destroyTenantRoute,
    download as downloadTenantRoute,
    markAllRead as markAllReadTenantRoute,
} from '@/actions/App/Http/Controllers/Tenant/NotificationController';
import NotificationPanel from '@/components/notifications/NotificationPanel.vue';
import NotificationTrigger from '@/components/notifications/NotificationTrigger.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import type { AppNotification, NotificationData } from '@/types/auth';

const page = usePage();
const isBrowser = typeof window !== 'undefined';
const isEchoConfigured =
    isBrowser && window.__plannerateEchoConfigured === true;
const isOpen = ref(false);
const panelRef = ref<HTMLElement | null>(null);

const auth = computed(
    () =>
        page.props.auth as {
            user: { id: string };
            notifications?: AppNotification[] | null;
            unread_count?: number;
        },
);
const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string | null } | null;

    return tenant?.id ?? null;
});

const isTenantContext = computed(
    () => tenantId.value !== null && tenantId.value !== '',
);

const notifications = ref<AppNotification[]>(
    (auth.value.notifications ?? []) as AppNotification[],
);
const unreadCount = ref<number>(auth.value.unread_count ?? 0);

onClickOutside(panelRef, () => {
    isOpen.value = false;
});

const connectionStatus = isEchoConfigured
    ? useConnectionStatus()
    : ref<ConnectionStatus>('disconnected');

if (isEchoConfigured) {
    useEchoNotification<NotificationData>(
        `App.Models.User.${auth.value.user.id}`,
        (payload) => {
            if ((payload.tenant_id ?? null) !== tenantId.value) {
                return;
            }

            notifications.value.unshift({
                id: payload.id,
                read_at: null,
                data: {
                    title: payload.title,
                    message: payload.message,
                    notification_type: payload.notification_type,
                    action_url: payload.action_url,
                    download_url: payload.download_url,
                    download_name: payload.download_name,
                    tenant_id: payload.tenant_id,
                },
                created_at: new Date().toISOString(),
            });
            unreadCount.value += 1;
        },
    );
}

function routeUrl(
    action: 'markAllRead' | 'destroyAll' | 'destroy' | 'download',
    id?: string,
): string {
    if (isTenantContext.value) {
        if (action === 'markAllRead') {
            return tenantWayfinderPath(
                markAllReadTenantRoute.url(),
            );
        }

        if (action === 'destroyAll') {
            return tenantWayfinderPath(
                destroyAllTenantRoute.url(),
            );
        }

        if (action === 'destroy' && id !== undefined) {
            return tenantWayfinderPath(
                destroyTenantRoute.url({ id }),
            );
        }

        if (action === 'download' && id !== undefined) {
            return tenantWayfinderPath(
                downloadTenantRoute.url({ id }),
            );
        }
    }

    if (action === 'markAllRead') {
        return tenantWayfinderPath(markAllReadLandlordRoute.url());
    }

    if (action === 'destroyAll') {
        return tenantWayfinderPath(destroyAllLandlordRoute.url());
    }

    if (action === 'destroy' && id !== undefined) {
        return tenantWayfinderPath(destroyLandlordRoute.url(id));
    }

    if (action === 'download' && id !== undefined) {
        return tenantWayfinderPath(downloadLandlordRoute.url(id));
    }

    throw new Error(`Missing notification id for ${action} route.`);
}

function dayBucket(iso: string): string {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

    if (d.toDateString() === today.toDateString()) {
        return 'hoje';
    }

    if (d.toDateString() === yesterday.toDateString()) {
        return 'ontem';
    }

    return 'anteriores';
}

const bucketLabel: Record<string, string> = {
    hoje: 'Hoje',
    ontem: 'Ontem',
    anteriores: 'Anteriores',
};

const groupedNotifications = computed(() => {
    const order = ['hoje', 'ontem', 'anteriores'];
    const map: Record<string, AppNotification[]> = {};

    for (const n of notifications.value) {
        const key = dayBucket(n.created_at);
        (map[key] ??= []).push(n);
    }

    return order
        .filter((k) => map[k]?.length)
        .map((k) => ({ key: k, label: bucketLabel[k], items: map[k] }));
});

function markAllRead() {
    router.post(
        routeUrl('markAllRead'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                notifications.value.forEach((n) => {
                    n.read_at = n.read_at ?? new Date().toISOString();
                });
                unreadCount.value = 0;
            },
        },
    );
}

function destroyAll() {
    router.delete(routeUrl('destroyAll'), {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = [];
            unreadCount.value = 0;
        },
    });
}

function destroy(id: string) {
    router.delete(routeUrl('destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            const idx = notifications.value.findIndex((n) => n.id === id);

            if (idx !== -1) {
                const wasUnread = !notifications.value[idx].read_at;
                notifications.value.splice(idx, 1);

                if (wasUnread) {
                    unreadCount.value = Math.max(0, unreadCount.value - 1);
                }
            }
        },
    });
}

/**
 * URL de download da notificação. Tolera id ausente: uma notificação malformada
 * (ex.: payload de broadcast sem id) derrubava a renderização do painel inteiro —
 * o dropdown não abria. Sem id, o item aparece apenas sem link de download.
 */
const getDownloadUrl = (id: string | null | undefined): string =>
    id ? routeUrl('download', id) : '';
</script>

<template>
    <div ref="panelRef" class="relative">
        <NotificationTrigger
            :unread-count="unreadCount"
            @toggle="isOpen = !isOpen"
        />

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="scale-95 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-95 opacity-0"
        >
            <NotificationPanel
                v-if="isOpen"
                :grouped-notifications="groupedNotifications"
                :notification-count="notifications.length"
                :unread-count="unreadCount"
                :connection-status="connectionStatus"
                :get-download-url="getDownloadUrl"
                @close="isOpen = false"
                @mark-all-read="markAllRead"
                @destroy-all="destroyAll"
                @destroy="destroy"
            />
        </Transition>
    </div>
</template>
