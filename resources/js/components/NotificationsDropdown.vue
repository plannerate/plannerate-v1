<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { useConnectionStatus, useEchoNotification } from '@laravel/echo-vue';
import {
    AlertTriangle,
    Bell,
    CheckCheck,
    CheckCircle2,
    Download,
    Info,
    Loader2,
    Trash2,
    Wifi,
    WifiOff,
    X,
    XCircle,
} from 'lucide-vue-next';
import {
    destroyAll as destroyAllRoute,
    destroy as destroyRoute,
    download as downloadRoute,
    markAllRead as markAllReadRoute,
} from '@/actions/App/Http/Controllers/Tenant/NotificationController';
import type { AppNotification, NotificationData } from '@/types/auth';

const page = usePage();
const subdomain = window.location.hostname.split('.')[0];
const isOpen = ref(false);
const panelRef = ref<HTMLElement | null>(null);

const auth = computed(() => page.props.auth as { user: { id: string }; notifications?: AppNotification[] | null; unread_count?: number });

const notifications = ref<AppNotification[]>((auth.value.notifications ?? []) as AppNotification[]);
const unreadCount = ref<number>(auth.value.unread_count ?? 0);

onClickOutside(panelRef, () => { isOpen.value = false; });

const connectionStatus = useConnectionStatus();

useEchoNotification<NotificationData>(
    `App.Models.User.${auth.value.user.id}`,
    (payload) => {
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
            },
            created_at: new Date().toISOString(),
        });
        unreadCount.value += 1;
    },
);

// --- Date helpers ---

function relativeTime(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'agora';
    if (mins < 60) return `${mins}min atrás`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h atrás`;
    const days = Math.floor(hrs / 24);
    if (days === 1) return '1d atrás';
    if (days < 7) return `${days}d atrás`;
    return new Date(iso).toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' });
}

function dayBucket(iso: string): string {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'hoje';
    if (d.toDateString() === yesterday.toDateString()) return 'ontem';
    return 'anteriores';
}

const bucketLabel: Record<string, string> = { hoje: 'Hoje', ontem: 'Ontem', anteriores: 'Anteriores' };

const groupedNotifications = computed(() => {
    const order = ['hoje', 'ontem', 'anteriores'];
    const map: Record<string, AppNotification[]> = {};
    for (const n of notifications.value) {
        const key = dayBucket(n.created_at);
        (map[key] ??= []).push(n);
    }
    return order.filter((k) => map[k]?.length).map((k) => ({ key: k, label: bucketLabel[k], items: map[k] }));
});

// --- Connection status ---

const connConfig = computed(() => {
    const map = {
        connected: { label: 'Conectado', icon: Wifi, color: 'text-green-500' },
        connecting: { label: 'Conectando…', icon: Loader2, color: 'text-yellow-500' },
        reconnecting: { label: 'Reconectando…', icon: Loader2, color: 'text-yellow-500' },
        disconnected: { label: 'Desconectado', icon: WifiOff, color: 'text-muted-foreground' },
        failed: { label: 'Falha', icon: WifiOff, color: 'text-destructive' },
    } as const;
    return map[connectionStatus.value] ?? map.disconnected;
});

// --- Icon / color per type ---

const typeIcon = (type: NotificationData['notification_type']) =>
    ({ info: Info, success: CheckCircle2, warning: AlertTriangle, error: XCircle }[type] ?? Info);

const typeIconBg = (type: NotificationData['notification_type']) =>
    ({
        info: 'bg-blue-500/10 dark:bg-blue-500/15',
        success: 'bg-green-500/10 dark:bg-green-500/15',
        warning: 'bg-yellow-500/10 dark:bg-yellow-500/15',
        error: 'bg-destructive/10 dark:bg-destructive/15',
    }[type] ?? 'bg-blue-500/10');

const typeIconColor = (type: NotificationData['notification_type']) =>
    ({
        info: 'text-blue-600 dark:text-blue-400',
        success: 'text-green-600 dark:text-green-400',
        warning: 'text-yellow-600 dark:text-yellow-400',
        error: 'text-destructive',
    }[type] ?? 'text-blue-600');

// --- Actions ---

function markAllRead() {
    router.post(markAllReadRoute.url(subdomain), {}, {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value.forEach((n) => { n.read_at = n.read_at ?? new Date().toISOString(); });
            unreadCount.value = 0;
        },
    });
}

function destroyAll() {
    router.delete(destroyAllRoute.url(subdomain), {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = [];
            unreadCount.value = 0;
        },
    });
}

function destroy(id: string) {
    router.delete(destroyRoute.url({ subdomain, id }), {
        preserveScroll: true,
        onSuccess: () => {
            const idx = notifications.value.findIndex((n) => n.id === id);
            if (idx !== -1) {
                const wasUnread = !notifications.value[idx].read_at;
                notifications.value.splice(idx, 1);
                if (wasUnread) unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        },
    });
}

const getDownloadUrl = (id: string) => downloadRoute.url({ subdomain, id });
</script>

<template>
    <div ref="panelRef" class="relative">
        <!-- Trigger -->
        <button
            type="button"
            class="relative flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            :aria-label="`Notificações${unreadCount > 0 ? ` (${unreadCount} não lidas)` : ''}`"
            @click="isOpen = !isOpen"
        >
            <Bell class="size-4" />
            <span
                v-if="unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] font-bold leading-none text-primary-foreground"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Panel -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="scale-95 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                role="dialog"
                aria-label="Painel de notificações"
                class="absolute right-0 top-[calc(100%+8px)] z-50 w-80 origin-top-right overflow-hidden rounded-xl border border-border bg-popover shadow-xl shadow-black/10 ring-1 ring-border/40"
            >
                <!-- Header -->
                <div class="flex items-center justify-between gap-2 border-b border-border/60 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-foreground">Notificações</h3>
                        <span
                            class="flex items-center gap-1 text-[10px] font-medium text-muted-foreground"
                            :title="connConfig.label"
                        >
                            <component
                                :is="connConfig.icon"
                                :class="['size-2.5', connConfig.color, connectionStatus === 'connecting' || connectionStatus === 'reconnecting' ? 'animate-spin' : '']"
                            />
                            <span class="hidden sm:inline">{{ connConfig.label }}</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            title="Marcar todas como lidas"
                            class="flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            @click="markAllRead"
                        >
                            <CheckCheck class="size-3" />
                            <span>Ler todas</span>
                        </button>
                        <button
                            v-if="notifications.length > 0"
                            type="button"
                            title="Limpar todas as notificações"
                            class="flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium text-destructive/70 transition-colors hover:bg-destructive/10 hover:text-destructive"
                            @click="destroyAll"
                        >
                            <Trash2 class="size-3" />
                        </button>
                        <button
                            type="button"
                            title="Fechar"
                            class="flex size-6 items-center justify-center rounded text-muted-foreground/60 transition-colors hover:bg-accent hover:text-foreground"
                            @click="isOpen = false"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- List -->
                <div class="max-h-[380px] overflow-y-auto overscroll-contain">
                    <template v-if="notifications.length > 0">
                        <div v-for="group in groupedNotifications" :key="group.key">
                            <!-- Day header (sticky) -->
                            <div class="sticky top-0 z-10 border-b border-border/40 bg-popover/80 px-4 py-1.5 backdrop-blur-sm">
                                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                                    {{ group.label }}
                                </span>
                            </div>

                            <!-- Items -->
                            <div
                                v-for="n in group.items"
                                :key="n.id"
                                :class="[
                                    'group/item flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-accent/50',
                                    !n.read_at && 'bg-primary/[0.03] dark:bg-primary/[0.06]',
                                ]"
                            >
                                <!-- Icon bg -->
                                <div :class="['mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg', typeIconBg(n.data.notification_type)]">
                                    <component :is="typeIcon(n.data.notification_type)" :class="['size-3.5', typeIconColor(n.data.notification_type)]" />
                                </div>

                                <!-- Content -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p :class="['truncate text-sm leading-tight', !n.read_at ? 'font-semibold text-foreground' : 'font-medium text-foreground/80']">
                                            {{ n.data.title }}
                                        </p>
                                        <span v-if="!n.read_at" class="mt-1 size-1.5 shrink-0 rounded-full bg-primary" title="Não lida" />
                                    </div>
                                    <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ n.data.message }}</p>
                                    <a
                                        v-if="n.data.download_url"
                                        :href="getDownloadUrl(n.id)"
                                        class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                        @click.stop
                                    >
                                        <Download class="size-3" />
                                        Baixar
                                    </a>
                                    <p class="mt-1 text-[10px] text-muted-foreground/60">{{ relativeTime(n.created_at) }}</p>
                                </div>

                                <!-- Remove on hover -->
                                <button
                                    type="button"
                                    title="Remover notificação"
                                    class="flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground/40 opacity-0 transition-[opacity,colors] hover:bg-destructive/10 hover:text-destructive group-hover/item:opacity-100"
                                    @click.stop="destroy(n.id)"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </template>

                    <div v-else class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
                        <Bell class="size-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">Nenhuma notificação</p>
                    </div>
                </div>

                <!-- Footer -->
                <div v-if="notifications.length > 0" class="border-t border-border/40 px-4 py-2 text-[11px] text-muted-foreground">
                    {{ notifications.length }} notificaç{{ notifications.length === 1 ? 'ão' : 'ões' }}
                    <template v-if="unreadCount > 0">
                        · <span class="font-semibold text-primary">{{ unreadCount }} não {{ unreadCount === 1 ? 'lida' : 'lidas' }}</span>
                    </template>
                </div>
            </div>
        </Transition>
    </div>
</template>
