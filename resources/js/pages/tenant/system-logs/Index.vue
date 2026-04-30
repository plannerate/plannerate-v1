<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import SystemLogController from '@/actions/App/Http/Controllers/Tenant/SystemLogController';
import DeleteButton from '@/components/DeleteButton.vue';
import { Badge } from '@/components/ui/badge';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type LogEntry = {
    timestamp: string;
    environment: string;
    level: string;
    message: string;
    is_key_point: boolean;
};

const props = defineProps<{
    subdomain: string;
    entries: LogEntry[];
    filters: {
        search: string;
        level: string;
        key_only: boolean;
        from: string;
        to: string;
        file: string;
    };
    summary: {
        total: number;
        filtered: number;
    };
    levels: string[];
    files: string[];
}>();

const { t } = useT();
const indexPath = SystemLogController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const clearPath = SystemLogController.clear.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const activeFilterParams = computed(() => ({
    search: props.filters.search,
    level: props.filters.level,
    key_only: props.filters.key_only ? '1' : '',
    file: props.filters.file,
}));

const clearHref = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.file !== '') {
        params.set('file', props.filters.file);
    }

    return params.toString() === '' ? clearPath : `${clearPath}?${params.toString()}`;
});

function formatDateTimeLocal(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    const hour = `${date.getHours()}`.padStart(2, '0');
    const minute = `${date.getMinutes()}`.padStart(2, '0');

    return `${year}-${month}-${day}T${hour}:${minute}`;
}

function buildPresetHref(hoursBack: number): string {
    const now = new Date();
    const from = new Date(now.getTime() - hoursBack * 60 * 60 * 1000);
    const params = new URLSearchParams();

    if (activeFilterParams.value.search !== '') {
        params.set('search', activeFilterParams.value.search);
    }

    if (activeFilterParams.value.level !== '') {
        params.set('level', activeFilterParams.value.level);
    }

    if (activeFilterParams.value.key_only !== '') {
        params.set('key_only', activeFilterParams.value.key_only);
    }

    if (activeFilterParams.value.file !== '') {
        params.set('file', activeFilterParams.value.file);
    }

    params.set('from', formatDateTimeLocal(from));
    params.set('to', formatDateTimeLocal(now));

    return `${indexPath}?${params.toString()}`;
}

const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

const pageMeta = useCrudPageMeta({
    headTitle: 'Logs do sistema',
    title: 'Logs do sistema',
    description: 'Visualize eventos críticos e limpe o arquivo de log do sistema.',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: 'Logs do sistema', href: indexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <div class="mx-auto w-full max-w-7xl space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">Total: {{ summary.total }}</Badge>
                    <Badge variant="secondary">Exibidos: {{ summary.filtered }}</Badge>
                </div>
                <DeleteButton :href="clearHref" :label="props.filters.file" :require-confirm-word="true">
                    Limpar logs
                </DeleteButton>
            </div>

            <form :action="indexPath" method="get" class="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                <div v-if="isMounted" class="mb-3 flex flex-wrap gap-2">
                    <a :href="buildPresetHref(24)" class="rounded-md border border-border px-2.5 py-1 text-xs text-foreground transition hover:bg-muted/50">
                        Últimas 24h
                    </a>
                    <a :href="buildPresetHref(24 * 7)" class="rounded-md border border-border px-2.5 py-1 text-xs text-foreground transition hover:bg-muted/50">
                        Últimos 7 dias
                    </a>
                    <a :href="buildPresetHref(24 * 30)" class="rounded-md border border-border px-2.5 py-1 text-xs text-foreground transition hover:bg-muted/50">
                        Últimos 30 dias
                    </a>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <input
                        type="text"
                        name="search"
                        :value="filters.search"
                        placeholder="Buscar por termo (SQLSTATE, sync, exception...)"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-6"
                    />
                    <select
                        name="file"
                        :value="filters.file"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    >
                        <option v-for="file in files" :key="file" :value="file">
                            {{ file }}
                        </option>
                    </select>
                    <select
                        name="level"
                        :value="filters.level"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    >
                        <option value="">Todos os níveis</option>
                        <option v-for="level in levels" :key="level" :value="level">
                            {{ level.toUpperCase() }}
                        </option>
                    </select>
                    <input
                        type="datetime-local"
                        name="from"
                        :value="filters.from"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    />
                    <input
                        type="datetime-local"
                        name="to"
                        :value="filters.to"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    />
                    <label class="flex items-center gap-2 rounded-lg border border-border px-3 text-sm md:col-span-2">
                        <input type="checkbox" name="key_only" value="1" :checked="filters.key_only" class="accent-primary" />
                        Só pontos-chave
                    </label>
                    <button
                        type="submit"
                        class="h-9 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 md:col-span-1"
                    >
                        Filtrar
                    </button>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/30 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Data</th>
                            <th class="px-4 py-3 font-medium">Nível</th>
                            <th class="px-4 py-3 font-medium">Ambiente</th>
                            <th class="px-4 py-3 font-medium">Mensagem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="entries.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                Nenhum log encontrado para os filtros aplicados.
                            </td>
                        </tr>
                        <tr
                            v-for="(entry, index) in entries"
                            :key="`${entry.timestamp}-${index}`"
                            class="border-t border-sidebar-border/60 align-top transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                        >
                            <td class="whitespace-nowrap px-4 py-3">{{ entry.timestamp || '-' }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="entry.level === 'error' || entry.level === 'critical' ? 'destructive' : 'outline'">
                                    {{ entry.level.toUpperCase() }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">{{ entry.environment || '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="line-clamp-3 whitespace-pre-wrap break-words">
                                    {{ entry.message || '-' }}
                                </div>
                                <Badge v-if="entry.is_key_point" variant="secondary" class="mt-2">
                                    ponto-chave
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
