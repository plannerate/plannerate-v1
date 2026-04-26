<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useEchoNotification } from '@laravel/echo-vue';
import { AlertTriangle, Bell, CheckCircle2, Download, Info, Trash2, XCircle } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    destroy as destroyRoute,
    download as downloadRoute,
    markAllRead as markAllReadRoute,
    markRead as markReadRoute,
} from '@/actions/App/Http/Controllers/Tenant/NotificationController';
import type { AppNotification, NotificationData } from '@/types/auth';

const page = usePage();

const auth = computed(() => page.props.auth as { user: { id: string }; notifications?: AppNotification[] | null; unread_count?: number });
const subdomain = window.location.hostname.split('.')[0];

const notifications = ref<AppNotification[]>((auth.value.notifications ?? []) as AppNotification[]);
const unreadCount = ref<number>(auth.value.unread_count ?? 0);

useEchoNotification<NotificationData>(
    `App.Models.User.${auth.value.user.id}`,
    (payload) => {
        notifications.value.unshift({
            id: payload.id,
            read_at: null,
            data: {
                title: payload.title,
                message: payload.message,
                type: payload.type,
                action_url: payload.action_url,
                download_url: payload.download_url,
                download_name: payload.download_name,
            },
            created_at: 'agora',
        });
        unreadCount.value += 1;
    },
);

const typeIcon = (type: NotificationData['type']) => {
    const map = {
        info: Info,
        success: CheckCircle2,
        warning: AlertTriangle,
        error: XCircle,
    };
    return map[type] ?? Info;
};

const typeColor = (type: NotificationData['type']) => {
    const map = {
        info: 'text-blue-500',
        success: 'text-green-500',
        warning: 'text-yellow-500',
        error: 'text-destructive',
    };
    return map[type] ?? 'text-blue-500';
};

function markRead(id: string) {
    router.patch(markReadRoute.url({ subdomain, id }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const n = notifications.value.find((item) => item.id === id);
            if (n && !n.read_at) {
                n.read_at = new Date().toISOString();
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        },
    });
}

function markAllRead() {
    router.post(markAllReadRoute.url(subdomain), {}, {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value.forEach((n) => {
                n.read_at = n.read_at ?? new Date().toISOString();
            });
            unreadCount.value = 0;
        },
    });
}

function destroy(id: string) {
    router.delete(destroyRoute.url({ subdomain, id }), {
        preserveScroll: true,
        onSuccess: () => {
            const index = notifications.value.findIndex((item) => item.id === id);
            if (index !== -1) {
                const wasUnread = !notifications.value[index].read_at;
                notifications.value.splice(index, 1);
                if (wasUnread) {
                    unreadCount.value = Math.max(0, unreadCount.value - 1);
                }
            }
        },
    });
}

const getDownloadUrl = (id: string) => downloadRoute.url({ subdomain, id });
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative" aria-label="Notificações">
                <Bell class="size-5" />
                <Badge
                    v-if="unreadCount > 0"
                    class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] leading-none"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </Badge>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-96 p-0">
            <div class="flex items-center justify-between px-4 py-3">
                <DropdownMenuLabel class="p-0 text-sm font-semibold">
                    Notificações
                </DropdownMenuLabel>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-auto px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllRead"
                >
                    Marcar todas como lidas
                </Button>
            </div>

            <DropdownMenuSeparator class="m-0" />

            <div class="max-h-[420px] overflow-y-auto">
                <template v-if="notifications.length > 0">
                    <div
                        v-for="n in notifications"
                        :key="n.id"
                        :class="[
                            'group flex gap-3 px-4 py-3 transition-colors hover:bg-muted/50',
                            !n.read_at && 'bg-primary/5',
                        ]"
                    >
                        <div class="mt-0.5 shrink-0">
                            <component
                                :is="typeIcon(n.data.type)"
                                :class="['size-4', typeColor(n.data.type)]"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                :class="[
                                    'truncate text-sm leading-snug',
                                    !n.read_at ? 'font-semibold text-foreground' : 'text-foreground/80',
                                ]"
                            >
                                {{ n.data.title }}
                            </p>
                            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                {{ n.data.message }}
                            </p>
                            <p class="mt-1 text-[11px] text-muted-foreground/70">
                                {{ n.created_at }}
                            </p>

                            <div class="mt-2 flex items-center gap-1">
                                <a
                                    v-if="n.data.download_url"
                                    :href="getDownloadUrl(n.id)"
                                    class="inline-flex items-center gap-1 rounded-sm px-2 py-1 text-xs font-medium text-primary hover:bg-primary/10 transition-colors"
                                    @click.stop
                                >
                                    <Download class="size-3" />
                                    Baixar
                                </a>

                                <Button
                                    v-if="!n.read_at"
                                    variant="ghost"
                                    size="sm"
                                    class="h-auto px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                                    @click.stop="markRead(n.id)"
                                >
                                    <CheckCircle2 class="mr-1 size-3" />
                                    Lida
                                </Button>

                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-auto px-2 py-1 text-xs text-muted-foreground hover:text-destructive"
                                    @click.stop="destroy(n.id)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-else class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
                    <Bell class="size-8 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">Nenhuma notificação</p>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
