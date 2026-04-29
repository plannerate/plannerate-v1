<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useConnectionStatus, useEchoNotification } from '@laravel/echo-vue';
import type { ConnectionStatus } from '@laravel/echo-vue';
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    CheckCircle2,
    Info,
    Loader2,
    Send,
    Wifi,
    WifiOff,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ReverbTestController from '@/actions/App/Http/Controllers/Tenant/ReverbTestController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppNotification, NotificationData } from '@/types/auth';

const props = defineProps<{
    user: { id: string; name: string; email: string };
}>();

const page = usePage();
const isBrowser = typeof window !== 'undefined';
const subdomain = isBrowser ? window.location.hostname.split('.')[0] : '';

const connectionStatus = isBrowser ? useConnectionStatus() : ref<ConnectionStatus>('disconnected');

const receivedNotifications = ref<Array<AppNotification & { received_at: string }>>([]);

if (isBrowser) {
    useEchoNotification<NotificationData>(
        `App.Models.User.${props.user.id}`,
        (payload) => {
            receivedNotifications.value.unshift({
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
                created_at: new Date().toLocaleTimeString('pt-BR'),
                received_at: new Date().toLocaleTimeString('pt-BR'),
            });
        },
    );
}

const form = useForm({
    title: '',
    message: '',
    type: 'info' as NotificationData['notification_type'],
    download_url: '',
    download_name: '',
});

function sendNotification() {
    form.post(ReverbTestController.notify.url(subdomain), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const statusConfig = computed(() => {
    const map: Record<ConnectionStatus, { label: string; icon: typeof Wifi; color: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
        connected: { label: 'Conectado', icon: Wifi, color: 'text-green-500', variant: 'default' },
        connecting: { label: 'Conectando…', icon: Loader2, color: 'text-yellow-500', variant: 'secondary' },
        reconnecting: { label: 'Reconectando…', icon: Loader2, color: 'text-yellow-500', variant: 'secondary' },
        disconnected: { label: 'Desconectado', icon: WifiOff, color: 'text-muted-foreground', variant: 'outline' },
        failed: { label: 'Falha na conexão', icon: AlertCircle, color: 'text-destructive', variant: 'destructive' },
    };

    return map[connectionStatus.value] ?? map.disconnected;
});

const typeIconMap = {
    info: Info,
    success: CheckCircle2,
    warning: AlertTriangle,
    error: XCircle,
};

const typeColorMap = {
    info: 'text-blue-500',
    success: 'text-green-500',
    warning: 'text-yellow-500',
    error: 'text-destructive',
};
</script>

<template>
    <Head title="Teste Reverb" />
    <AppLayout
        :breadcrumbs="[{ title: 'Dashboard', href: '/' }, { title: 'Teste Reverb', href: ReverbTestController.index.url(subdomain) }]"
        :page-header="{ title: 'Teste Reverb / WebSocket', description: 'Verifique a conexão e dispare notificações em tempo real' }"
    >
        <div class="flex flex-col gap-6 p-4">

            <!-- Status da Conexão -->
            <div class="rounded-[var(--radius)] border border-border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Status do WebSocket</h2>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            Conectado como <span class="font-medium text-foreground">{{ user.name }}</span>
                            — canal <code class="rounded bg-muted px-1.5 py-0.5 text-xs">App.Models.User.{{ user.id }}</code>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <component
                            :is="statusConfig.icon"
                            :class="['size-5', statusConfig.color, connectionStatus === 'connecting' || connectionStatus === 'reconnecting' ? 'animate-spin' : '']"
                        />
                        <Badge :variant="statusConfig.variant">
                            {{ statusConfig.label }}
                        </Badge>
                    </div>
                </div>

                <!-- Indicador visual de pulso quando conectado -->
                <div v-if="connectionStatus === 'connected'" class="mt-4 flex items-center gap-2">
                    <span class="relative flex size-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                        <span class="relative inline-flex size-3 rounded-full bg-green-500" />
                    </span>
                    <span class="text-xs text-muted-foreground">Ouvindo notificações em tempo real</span>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">

                <!-- Formulário de disparo -->
                <div class="rounded-[var(--radius)] border border-border bg-card p-6">
                    <h2 class="mb-4 text-base font-semibold text-foreground">Disparar Notificação</h2>
                    <form class="flex flex-col gap-4" @submit.prevent="sendNotification">

                        <div class="flex flex-col gap-1.5">
                            <Label for="title">Título</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Ex: Exportação concluída"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label for="message">Mensagem</Label>
                            <Textarea
                                id="message"
                                v-model="form.message"
                                rows="3"
                                placeholder="Ex: Seu arquivo está pronto para download"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.message" class="text-xs text-destructive">{{ form.errors.message }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label>Tipo</Label>
                            <Select v-model="form.type" :disabled="form.processing">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecione o tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="info">
                                        <span class="flex items-center gap-2">
                                            <Info class="size-4 text-blue-500" /> Info
                                        </span>
                                    </SelectItem>
                                    <SelectItem value="success">
                                        <span class="flex items-center gap-2">
                                            <CheckCircle2 class="size-4 text-green-500" /> Sucesso
                                        </span>
                                    </SelectItem>
                                    <SelectItem value="warning">
                                        <span class="flex items-center gap-2">
                                            <AlertTriangle class="size-4 text-yellow-500" /> Aviso
                                        </span>
                                    </SelectItem>
                                    <SelectItem value="error">
                                        <span class="flex items-center gap-2">
                                            <XCircle class="size-4 text-destructive" /> Erro
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="rounded-[var(--radius)] border border-dashed border-border p-4">
                            <p class="mb-3 text-xs font-medium text-muted-foreground">Download (opcional)</p>
                            <div class="flex flex-col gap-3">
                                <div class="flex flex-col gap-1.5">
                                    <Label for="download_url" class="text-xs">Caminho do arquivo</Label>
                                    <Input
                                        id="download_url"
                                        v-model="form.download_url"
                                        placeholder="Ex: exports/relatorio.xlsx"
                                        class="text-sm"
                                        :disabled="form.processing"
                                    />
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label for="download_name" class="text-xs">Nome para download</Label>
                                    <Input
                                        id="download_name"
                                        v-model="form.download_name"
                                        placeholder="Ex: relatorio.xlsx"
                                        class="text-sm"
                                        :disabled="form.processing"
                                    />
                                </div>
                            </div>
                        </div>

                        <Button type="submit" :disabled="form.processing" class="w-full">
                            <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <Send v-else class="mr-2 size-4" />
                            Enviar notificação
                        </Button>
                    </form>
                </div>

                <!-- Feed de notificações recebidas -->
                <div class="rounded-[var(--radius)] border border-border bg-card p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-foreground">Notificações Recebidas</h2>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="receivedNotifications.length > 0"
                                class="flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                            >
                                {{ receivedNotifications.length }}
                            </span>
                            <Button
                                v-if="receivedNotifications.length > 0"
                                variant="ghost"
                                size="sm"
                                class="h-auto px-2 py-1 text-xs text-muted-foreground"
                                @click="receivedNotifications = []"
                            >
                                Limpar
                            </Button>
                        </div>
                    </div>

                    <div class="flex max-h-[460px] flex-col gap-2 overflow-y-auto">
                        <template v-if="receivedNotifications.length > 0">
                            <div
                                v-for="n in receivedNotifications"
                                :key="n.id"
                                class="flex animate-in fade-in-0 slide-in-from-top-2 gap-3 rounded-[var(--radius)] border border-border bg-background p-3 duration-300"
                            >
                                <component
                                    :is="typeIconMap[n.data.notification_type] ?? Info"
                                    :class="['mt-0.5 size-4 shrink-0', typeColorMap[n.data.notification_type] ?? 'text-blue-500']"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium text-foreground">{{ n.data.title }}</p>
                                        <span class="shrink-0 text-[11px] text-muted-foreground">{{ n.received_at }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ n.data.message }}</p>
                                    <p v-if="n.data.download_url" class="mt-1 flex items-center gap-1 text-[11px] text-primary">
                                        <span class="font-medium">Download:</span> {{ n.data.download_url }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <div v-else class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                            <Bell class="size-10 text-muted-foreground/30" />
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Nenhuma notificação recebida</p>
                                <p class="mt-0.5 text-xs text-muted-foreground/70">
                                    As notificações aparecem aqui em tempo real via WebSocket
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
