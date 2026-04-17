<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Activity, AlertCircle, CheckCircle2, Clock, TrendingUp, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Stats {
    total_days: number;
    success: number;
    failed: number;
    skipped: number;
    pending: number;
    total_items: number;
    needs_retry: number;
    consecutive_failures: number;
}

interface Log {
    id: string;
    sync_type: string;
    sync_date: string;
    status: string;
    retry_count: number;
    consecutive_failures: number;
    total_items: number | null;
    error_message: string | null;
    store_name: string;
    created_at: string;
    can_retry?: boolean;
    should_skip?: boolean;
}

interface Props {
    stats: {
        sales: Stats;
        products: Stats;
        purchases: Stats;
    };
    recentLogs: Log[];
    failedDays: Log[];
    timeline: any[];
    client: {
        id: string;
        name: string;
    };
}

const props = defineProps<Props>();

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        skipped: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        pending: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const getSyncTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        sales: 'Vendas',
        products: 'Produtos',
        purchases: 'Compras',
    };
    return labels[type] || type;
};

const getStatusIcon = (status: string) => {
    if (status === 'success') return CheckCircle2;
    if (status === 'failed') return XCircle;
    if (status === 'skipped') return AlertCircle;
    return Clock;
};

const totalSuccess = computed(() => {
    return props.stats.sales.success + props.stats.products.success + props.stats.purchases.success;
});

const totalFailed = computed(() => {
    return props.stats.sales.failed + props.stats.products.failed + props.stats.purchases.failed;
});

const totalSkipped = computed(() => {
    return props.stats.sales.skipped + props.stats.products.skipped + props.stats.purchases.skipped;
});

const successRate = computed(() => {
    const total = totalSuccess.value + totalFailed.value + totalSkipped.value;
    return total > 0 ? ((totalSuccess.value / total) * 100).toFixed(1) : '0.0';
});
</script>

<template>
    <AppLayout>
        <Head title="Dashboard de Integrações" />

        <div class="space-y-6 p-4">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold tracking-tight dark:text-white">
                    Dashboard de Integrações
                </h1>
                <p class="text-muted-foreground mt-2">
                    Acompanhe o status das sincronizações de {{ client.name }}
                </p>
            </div>

            <!-- Cards de Resumo -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Taxa de Sucesso</CardTitle>
                        <TrendingUp class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ successRate }}%</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ totalSuccess }} de {{ totalSuccess + totalFailed + totalSkipped }} syncs
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Falhas</CardTitle>
                        <XCircle class="h-4 w-4 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalFailed }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ stats.sales.needs_retry + stats.products.needs_retry }} precisam retry
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Dias Pulados</CardTitle>
                        <AlertCircle class="h-4 w-4 text-yellow-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalSkipped }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Máx 5 tentativas atingido
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Itens Sincronizados</CardTitle>
                        <Activity class="h-4 w-4 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ (stats.sales.total_items + stats.products.total_items).toLocaleString('pt-BR') }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Total de registros
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Stats por Tipo -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="(stat, type) in stats" :key="type">
                    <CardHeader>
                        <CardTitle>{{ getSyncTypeLabel(type as string) }}</CardTitle>
                        <CardDescription>Estatísticas de sincronização</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Total de dias:</span>
                            <span class="font-medium">{{ stat.total_days }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-green-600 dark:text-green-400">Sucessos:</span>
                            <span class="font-medium">{{ stat.success }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-red-600 dark:text-red-400">Falhas:</span>
                            <span class="font-medium">{{ stat.failed }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-yellow-600 dark:text-yellow-400">Pulados:</span>
                            <span class="font-medium">{{ stat.skipped }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t">
                            <span class="text-sm text-muted-foreground">Itens totais:</span>
                            <span class="font-medium">{{ stat.total_items.toLocaleString('pt-BR') }}</span>
                        </div>
                        <div v-if="stat.consecutive_failures > 0" class="flex justify-between text-red-600">
                            <span class="text-sm">Falhas consecutivas:</span>
                            <span class="font-medium">{{ stat.consecutive_failures }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Dias com Falhas -->
            <Card v-if="failedDays.length > 0">
                <CardHeader>
                    <CardTitle>Dias com Falhas</CardTitle>
                    <CardDescription>
                        Dias que falharam e podem precisar de retry manual
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Data</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Loja</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Tentativas</TableHead>
                                <TableHead>Erro</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="log in failedDays" :key="log.id">
                                <TableCell class="font-mono text-sm">{{ log.sync_date }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ getSyncTypeLabel(log.sync_type) }}</Badge>
                                </TableCell>
                                <TableCell>{{ log.store_name }}</TableCell>
                                <TableCell>
                                    <Badge :class="getStatusColor(log.status)">
                                        {{ log.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span :class="log.retry_count >= 5 ? 'text-red-600 font-bold' : ''">
                                        {{ log.retry_count }}/5
                                    </span>
                                </TableCell>
                                <TableCell class="max-w-xs truncate text-xs text-muted-foreground">
                                    {{ log.error_message || '-' }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Logs Recentes -->
            <Card>
                <CardHeader>
                    <CardTitle>Últimas Sincronizações</CardTitle>
                    <CardDescription>Logs das últimas 24 horas</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Data/Hora</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Data Sync</TableHead>
                                <TableHead>Loja</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Itens</TableHead>
                                <TableHead>Tentativas</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="log in recentLogs" :key="log.id">
                                <TableCell class="font-mono text-xs">{{ log.created_at }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ getSyncTypeLabel(log.sync_type) }}</Badge>
                                </TableCell>
                                <TableCell class="font-mono text-sm">{{ log.sync_date }}</TableCell>
                                <TableCell>{{ log.store_name }}</TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <component :is="getStatusIcon(log.status)" class="h-4 w-4" />
                                        <Badge :class="getStatusColor(log.status)">
                                            {{ log.status }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span v-if="log.total_items">{{ log.total_items.toLocaleString('pt-BR') }}</span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell>
                                    <span :class="log.retry_count > 0 ? 'text-yellow-600' : ''">
                                        {{ log.retry_count }}
                                    </span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
