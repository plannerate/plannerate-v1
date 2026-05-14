<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    Info,
    X,
    XCircle,
} from 'lucide-vue-next';
import type { AppNotification, NotificationData } from '@/types/auth';

defineProps<{
    notification: AppNotification;
    downloadUrl: string;
}>();

defineEmits<{
    destroy: [id: string];
}>();

function relativeTime(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);

    if (mins < 1) {
        return 'agora';
    }

    if (mins < 60) {
        return `${mins}min atrás`;
    }

    const hrs = Math.floor(mins / 60);

    if (hrs < 24) {
        return `${hrs}h atrás`;
    }

    const days = Math.floor(hrs / 24);

    if (days === 1) {
        return '1d atrás';
    }

    if (days < 7) {
        return `${days}d atrás`;
    }

    return new Date(iso).toLocaleDateString('pt-BR', {
        day: 'numeric',
        month: 'short',
    });
}

const typeIcon = (type: NotificationData['notification_type']) =>
    ({
        info: Info,
        success: CheckCircle2,
        warning: AlertTriangle,
        error: XCircle,
    })[type] ?? Info;

const typeIconBg = (type: NotificationData['notification_type']) =>
    ({
        info: 'bg-blue-500/10 dark:bg-blue-500/15',
        success: 'bg-green-500/10 dark:bg-green-500/15',
        warning: 'bg-yellow-500/10 dark:bg-yellow-500/15',
        error: 'bg-destructive/10 dark:bg-destructive/15',
    })[type] ?? 'bg-blue-500/10';

const typeIconColor = (type: NotificationData['notification_type']) =>
    ({
        info: 'text-blue-600 dark:text-blue-400',
        success: 'text-green-600 dark:text-green-400',
        warning: 'text-yellow-600 dark:text-yellow-400',
        error: 'text-destructive',
    })[type] ?? 'text-blue-600';
</script>

<template>
    <div
        :class="[
            'group/item flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-accent/50',
            !notification.read_at && 'bg-primary/[0.03] dark:bg-primary/[0.06]',
        ]"
    >
        <div
            :class="[
                'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg',
                typeIconBg(notification.data.notification_type),
            ]"
        >
            <component
                :is="typeIcon(notification.data.notification_type)"
                :class="[
                    'size-3.5',
                    typeIconColor(notification.data.notification_type),
                ]"
            />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <p
                    :class="[
                        'truncate text-sm leading-tight',
                        !notification.read_at
                            ? 'font-semibold text-foreground'
                            : 'font-medium text-foreground/80',
                    ]"
                >
                    {{ notification.data.title }}
                </p>
                <span
                    v-if="!notification.read_at"
                    class="mt-1 size-1.5 shrink-0 rounded-full bg-primary"
                    title="Não lida"
                />
            </div>
            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                {{ notification.data.message }}
            </p>
            <a
                v-if="notification.data.download_url"
                :href="downloadUrl"
                class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                @click.stop
            >
                <Download class="size-3" />
                Baixar
            </a>
            <p class="mt-1 text-[10px] text-muted-foreground/60">
                {{ relativeTime(notification.created_at) }}
            </p>
        </div>

        <button
            type="button"
            title="Remover notificação"
            class="flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground/40 opacity-0 transition-[opacity,colors] group-hover/item:opacity-100 hover:bg-destructive/10 hover:text-destructive"
            @click.stop="$emit('destroy', notification.id)"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
