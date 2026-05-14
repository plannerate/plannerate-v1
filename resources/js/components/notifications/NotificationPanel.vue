<script setup lang="ts">
import type { ConnectionStatus } from '@laravel/echo-vue';
import { CheckCheck, Loader2, Trash2, Wifi, WifiOff, X } from 'lucide-vue-next';
import { computed } from 'vue';
import NotificationEmptyState from '@/components/notifications/NotificationEmptyState.vue';
import NotificationFooter from '@/components/notifications/NotificationFooter.vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import type { AppNotification } from '@/types/auth';

type NotificationGroup = {
    key: string;
    label: string;
    items: AppNotification[];
};

const props = defineProps<{
    groupedNotifications: NotificationGroup[];
    notificationCount: number;
    unreadCount: number;
    connectionStatus: ConnectionStatus;
    getDownloadUrl: (id: string) => string;
}>();

defineEmits<{
    close: [];
    markAllRead: [];
    destroyAll: [];
    destroy: [id: string];
}>();

const connConfig = computed(() => {
    const map = {
        connected: { label: 'Conectado', icon: Wifi, color: 'text-green-500' },
        connecting: {
            label: 'Conectando…',
            icon: Loader2,
            color: 'text-yellow-500',
        },
        reconnecting: {
            label: 'Reconectando…',
            icon: Loader2,
            color: 'text-yellow-500',
        },
        disconnected: {
            label: 'Desconectado',
            icon: WifiOff,
            color: 'text-muted-foreground',
        },
        failed: { label: 'Falha', icon: WifiOff, color: 'text-destructive' },
    } as const;

    return map[props.connectionStatus] ?? map.disconnected;
});
</script>

<template>
    <div
        role="dialog"
        aria-label="Painel de notificações"
        class="absolute top-[calc(100%+8px)] right-0 z-50 w-80 origin-top-right overflow-hidden rounded-xl border border-border bg-popover shadow-xl ring-1 shadow-black/10 ring-border/40"
    >
        <div
            class="flex items-center justify-between gap-2 border-b border-border/60 px-4 py-3"
        >
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-foreground">
                    Notificações
                </h3>
                <span
                    class="flex items-center gap-1 text-[10px] font-medium text-muted-foreground"
                    :title="connConfig.label"
                >
                    <component
                        :is="connConfig.icon"
                        :class="[
                            'size-2.5',
                            connConfig.color,
                            connectionStatus === 'connecting' ||
                            connectionStatus === 'reconnecting'
                                ? 'animate-spin'
                                : '',
                        ]"
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
                    @click="$emit('markAllRead')"
                >
                    <CheckCheck class="size-3" />
                    <span class="sr-only">Ler todas</span>
                </button>
                <button
                    v-if="notificationCount > 0"
                    type="button"
                    title="Limpar todas as notificações"
                    class="flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium text-destructive/70 transition-colors hover:bg-destructive/10 hover:text-destructive"
                    @click="$emit('destroyAll')"
                >
                    <Trash2 class="size-3" />
                </button>
                <button
                    type="button"
                    title="Fechar"
                    class="flex size-6 items-center justify-center rounded text-muted-foreground/60 transition-colors hover:bg-accent hover:text-foreground"
                    @click="$emit('close')"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </div>

        <div class="max-h-[380px] overflow-y-auto overscroll-contain">
            <template v-if="notificationCount > 0">
                <div v-for="group in groupedNotifications" :key="group.key">
                    <div
                        class="sticky top-0 z-10 border-b border-border/40 bg-popover/80 px-4 py-1.5 backdrop-blur-sm"
                    >
                        <span
                            class="text-[10px] font-semibold tracking-widest text-muted-foreground/60 uppercase"
                        >
                            {{ group.label }}
                        </span>
                    </div>

                    <NotificationItem
                        v-for="notification in group.items"
                        :key="notification.id"
                        :notification="notification"
                        :download-url="getDownloadUrl(notification.id)"
                        @destroy="$emit('destroy', $event)"
                    />
                </div>
            </template>

            <NotificationEmptyState v-else />
        </div>

        <NotificationFooter
            :notification-count="notificationCount"
            :unread-count="unreadCount"
        />
    </div>
</template>
