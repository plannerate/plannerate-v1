<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
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
const indexPath = SystemLogController.index.url().replace(/^\/\/[^/]+/, '');
const clearPath = SystemLogController.clear.url().replace(/^\/\/[^/]+/, '');
const downloadPath = SystemLogController.download.url().replace(/^\/\/[^/]+/, '');
const form = ref({
    search: props.filters.search,
    file: props.filters.file,
    level: props.filters.level,
    from: props.filters.from,
    to: props.filters.to,
    key_only: props.filters.key_only,
});

watch(
    () => props.filters,
    (filters) => {
        form.value = {
            search: filters.search,
            file: filters.file,
            level: filters.level,
            from: filters.from,
            to: filters.to,
            key_only: filters.key_only,
        };
    },
    { deep: true },
);

const clearHref = computed(() => {
    const params = new URLSearchParams();

    if (form.value.file !== '') {
        params.set('file', form.value.file);
    }

    return params.toString() === '' ? clearPath : `${clearPath}?${params.toString()}`;
});

/**
 * Monta a query string com os filtros atualmente aplicados (vindos das props),
 * usada tanto para o download quanto para refletir o que está sendo exibido.
 */
const appliedFilterParams = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.file !== '') {
        params.set('file', props.filters.file);
    }
    if (props.filters.search !== '') {
        params.set('search', props.filters.search);
    }
    if (props.filters.level !== '') {
        params.set('level', props.filters.level);
    }
    if (props.filters.from !== '') {
        params.set('from', props.filters.from);
    }
    if (props.filters.to !== '') {
        params.set('to', props.filters.to);
    }
    if (props.filters.key_only) {
        params.set('key_only', '1');
    }

    return params;
});

/**
 * URL de download do log respeitando os filtros aplicados.
 */
const downloadHref = computed(() => {
    const query = appliedFilterParams.value.toString();

    return query === '' ? downloadPath : `${downloadPath}?${query}`;
});

/**
 * Reconstrói as entradas exibidas em texto, em ordem cronológica, para copiar.
 */
function buildLogText(): string {
    return [...props.entries]
        .reverse()
        .map((entry) => `[${entry.timestamp}] ${entry.environment}.${entry.level.toUpperCase()}: ${entry.message}`)
        .join('\n');
}

const copied = ref(false);

/**
 * Copia as entradas filtradas para a área de transferência.
 */
async function copyLogs(): Promise<void> {
    const text = buildLogText();

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function formatDateTimeLocal(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    const hour = `${date.getHours()}`.padStart(2, '0');
    const minute = `${date.getMinutes()}`.padStart(2, '0');

    return `${year}-${month}-${day}T${hour}:${minute}`;
}

function applyFilters(): void {
    router.get(indexPath, {
        search: form.value.search || undefined,
        file: form.value.file || undefined,
        level: form.value.level || undefined,
        from: form.value.from || undefined,
        to: form.value.to || undefined,
        key_only: form.value.key_only ? 1 : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function applyPreset(hoursBack: number): void {
    const now = new Date();
    const from = new Date(now.getTime() - hoursBack * 60 * 60 * 1000);

    form.value.from = formatDateTimeLocal(from);
    form.value.to = formatDateTimeLocal(now);

    applyFilters();
}

const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.system-logs.title'),
    title: t('app.tenant.system-logs.title'),
    description: t('app.tenant.system-logs.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.system-logs.navigation'), href: indexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <div class="mx-auto w-full max-w-7xl space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{{ t('app.tenant.system-logs.summary_total', { total: String(summary.total) }) }}</Badge>
                    <Badge variant="secondary">{{ t('app.tenant.system-logs.summary_filtered', { filtered: String(summary.filtered) }) }}</Badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium text-foreground transition hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="entries.length === 0"
                        @click="copyLogs"
                    >
                        {{ copied ? t('app.tenant.system-logs.copied_feedback') : t('app.tenant.system-logs.copy_button') }}
                    </button>
                    <a
                        :href="downloadHref"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium text-foreground transition hover:bg-muted/50"
                    >
                        {{ t('app.tenant.system-logs.download_button') }}
                    </a>
                    <DeleteButton :href="clearHref" :label="props.filters.file" :require-confirm-word="true">
                        {{ t('app.tenant.system-logs.clear_button') }}
                    </DeleteButton>
                </div>
            </div>

            <form @submit.prevent="applyFilters" class="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                <div v-if="isMounted" class="mb-3 flex flex-wrap gap-2">
                    <button type="button" class="rounded-md border border-border px-2.5 py-1 text-xs text-foreground transition hover:bg-muted/50" @click="applyPreset(24)">
                        {{ t('app.tenant.system-logs.preset_24h') }}
                    </button>
                    <button type="button" class="rounded-md border border-border px-2.5 py-1 text-xs text-foreground transition hover:bg-muted/50" @click="applyPreset(24 * 7)">
                        {{ t('app.tenant.system-logs.preset_7d') }}
                    </button>
                    <button type="button" class="rounded-md border border-border px-2.5 py-1 text-xs text-foreground transition hover:bg-muted/50" @click="applyPreset(24 * 30)">
                        {{ t('app.tenant.system-logs.preset_30d') }}
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <input
                        type="text"
                        name="search"
                        v-model="form.search"
                        :placeholder="t('app.tenant.system-logs.search_placeholder')"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-6"
                    />
                    <select
                        name="file"
                        v-model="form.file"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    >
                        <option v-for="file in files" :key="file" :value="file">
                            {{ file }}
                        </option>
                    </select>
                    <select
                        name="level"
                        v-model="form.level"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    >
                        <option value="">{{ t('app.tenant.system-logs.level_all') }}</option>
                        <option v-for="level in levels" :key="level" :value="level">
                            {{ level.toUpperCase() }}
                        </option>
                    </select>
                    <input
                        type="datetime-local"
                        name="from"
                        v-model="form.from"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    />
                    <input
                        type="datetime-local"
                        name="to"
                        v-model="form.to"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 md:col-span-2"
                    />
                    <label class="flex items-center gap-2 rounded-lg border border-border px-3 text-sm md:col-span-2">
                        <input v-model="form.key_only" type="checkbox" name="key_only" value="1" class="accent-primary" />
                        {{ t('app.tenant.system-logs.key_only_label') }}
                    </label>
                    <button
                        type="submit"
                        class="h-9 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 md:col-span-1"
                    >
                        {{ t('app.tenant.system-logs.filters.submit') }}
                    </button>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/30 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.system-logs.table.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.system-logs.table.level') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.system-logs.table.environment') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.system-logs.table.message') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="entries.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                {{ t('app.tenant.system-logs.messages.no_logs') }}
                            </td>
                        </tr>
                        <tr
                            v-for="(entry, index) in entries"
                            :key="`${entry.timestamp}-${index}`"
                            class="border-t border-sidebar-border/60 align-top transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
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
