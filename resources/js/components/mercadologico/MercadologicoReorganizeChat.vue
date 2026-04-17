<script setup lang="ts">
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { CheckCircle2, RotateCcw } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export interface ReorganizeLogItem {
    id: number;
    status: string;
    applied_at?: string | null;
    created_at: string;
    agent_response?: {
        renames?: Array<{ category_id: string; new_name: string }>;
        merges?: Array<{ keep_id: string; remove_id: string }>;
        disable?: string[];
        delete?: string[];
        reasoning?: string;
    };
}

interface Props {
    open: boolean;
    logs?: ReorganizeLogItem[];
    initialLog?: ReorganizeLogItem | null;
    applyUrl: string;
    restoreUrl: string;
    /** Query params para o backend colocar no redirect (expand, selected). */
    redirectExpand?: string;
    redirectSelected?: string;
}

const props = withDefaults(defineProps<Props>(), {
    logs: () => [],
    initialLog: null,
    redirectExpand: '',
    redirectSelected: '',
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    applied: [];
    restored: [];
}>();

const selectedLog = ref<ReorganizeLogItem | null>(null);

watch(
    () => props.initialLog,
    (log) => {
        if (log) {
            selectedLog.value = log;
        }
    },
    { immediate: true },
);

watch(
    () => props.logs,
    (logs) => {
        if (logs?.length && !selectedLog.value && props.initialLog) {
            selectedLog.value = props.initialLog;
        }
    },
    { immediate: true },
);

function selectLog(log: ReorganizeLogItem) {
    selectedLog.value = log;
}

function statusLabel(status: string): string {
    if (status === 'applied') return 'Aplicada';
    if (status === 'suggestion') return 'Sugestão';
    return status;
}

const applyingLogId = ref<number | null>(null);
const restoringLogId = ref<number | null>(null);

function redirectPayload(logId: number): Record<string, string | number> {
    const body: Record<string, string | number> = { log_id: logId };
    if (props.redirectExpand) body.expand = props.redirectExpand;
    if (props.redirectSelected) body.selected = props.redirectSelected;
    return body;
}

function handleApply(logId: number) {
    applyingLogId.value = logId;
    router.post(props.applyUrl, redirectPayload(logId), {
        preserveScroll: true,
        onFinish: () => {
            applyingLogId.value = null;
        },
        onSuccess: () => {
            emit('applied');
        },
    });
}

function handleRestore(logId: number) {
    restoringLogId.value = logId;
    router.post(props.restoreUrl, redirectPayload(logId), {
        preserveScroll: true,
        onFinish: () => {
            restoringLogId.value = null;
        },
        onSuccess: () => {
            emit('restored');
        },
    });
}

const displayLogs = computed(() => props.logs ?? []);
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="flex w-full flex-col p-0 sm:max-w-lg">
            <SheetHeader class="border-b border-border px-4 py-3">
                <SheetTitle class="text-base">Sugestões de reorganização (IA)</SheetTitle>
            </SheetHeader>
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div class="flex min-h-0 flex-1 gap-0">
                    <ul class="w-36 shrink-0 list-none border-r border-border bg-muted/20 p-2">
                        <li
                            v-for="log in displayLogs"
                            :key="log.id"
                            class="mb-1"
                        >
                            <button
                                type="button"
                                class="w-full rounded px-2 py-1.5 text-left text-xs transition-colors"
                                :class="selectedLog?.id === log.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted'"
                                @click="selectLog(log)"
                            >
                                <span class="block truncate">
                                    #{{ log.id }}
                                    <span class="text-muted-foreground">·</span>
                                    {{ statusLabel(log.status) }}
                                </span>
                                <span class="block truncate text-[10px] opacity-80">
                                    {{ log.created_at ? new Date(log.created_at).toLocaleString('pt-BR') : '' }}
                                </span>
                            </button>
                        </li>
                        <li v-if="displayLogs.length === 0" class="px-2 py-4 text-xs text-muted-foreground">
                            Nenhuma sugestão ainda. Clique em "Reorganizar categorias com IA".
                        </li>
                    </ul>
                    <div class="min-w-0 flex-1 overflow-y-auto p-4">
                        <template v-if="selectedLog">
                            <p class="mb-3 text-sm text-muted-foreground">
                                {{ selectedLog.agent_response?.reasoning || 'Sem justificativa.' }}
                            </p>
                            <div v-if="(selectedLog.agent_response?.renames?.length ?? 0) > 0" class="mb-4">
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide">Renomes ({{ selectedLog.agent_response?.renames?.length ?? 0 }})</h4>
                                <ul class="space-y-1 text-xs">
                                    <li
                                        v-for="(r, i) in (selectedLog.agent_response?.renames ?? [])"
                                        :key="i"
                                        class="rounded border border-border bg-muted/20 px-2 py-1"
                                    >
                                        <span class="font-mono text-muted-foreground">{{ r.category_id }}</span>
                                        → {{ r.new_name }}
                                    </li>
                                </ul>
                            </div>
                            <div v-if="(selectedLog.agent_response?.merges?.length ?? 0) > 0" class="mb-4">
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide">Fusões ({{ selectedLog.agent_response?.merges?.length ?? 0 }})</h4>
                                <ul class="space-y-1 text-xs">
                                    <li
                                        v-for="(m, i) in (selectedLog.agent_response?.merges ?? [])"
                                        :key="i"
                                        class="rounded border border-border bg-muted/20 px-2 py-1"
                                    >
                                        manter <span class="font-mono">{{ m.keep_id }}</span>, remover <span class="font-mono">{{ m.remove_id }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="(selectedLog.agent_response?.disable?.length ?? 0) > 0" class="mb-4">
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide">Desabilitar (draft) ({{ selectedLog.agent_response?.disable?.length ?? 0 }})</h4>
                                <ul class="space-y-1 text-xs">
                                    <li
                                        v-for="(id, i) in (selectedLog.agent_response?.disable ?? [])"
                                        :key="i"
                                        class="rounded border border-border bg-muted/20 px-2 py-1"
                                    >
                                        <span class="font-mono text-muted-foreground">{{ id }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="(selectedLog.agent_response?.delete?.length ?? 0) > 0" class="mb-4">
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide">Excluir (soft) ({{ selectedLog.agent_response?.delete?.length ?? 0 }})</h4>
                                <ul class="space-y-1 text-xs">
                                    <li
                                        v-for="(id, i) in (selectedLog.agent_response?.delete ?? [])"
                                        :key="i"
                                        class="rounded border border-border bg-muted/20 px-2 py-1"
                                    >
                                        <span class="font-mono text-muted-foreground">{{ id }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="selectedLog.status === 'suggestion'"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                                    :disabled="applyingLogId !== null"
                                    @click="handleApply(selectedLog.id)"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                    {{ applyingLogId === selectedLog.id ? 'Aplicando…' : 'Aplicar esta sugestão' }}
                                </button>
                                <button
                                    v-if="selectedLog.status === 'applied'"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium hover:bg-muted disabled:opacity-50"
                                    :disabled="restoringLogId !== null"
                                    title="Restaurar backup (reverter alterações)"
                                    @click="handleRestore(selectedLog.id)"
                                >
                                    <RotateCcw class="size-3.5" />
                                    {{ restoringLogId === selectedLog.id ? 'Restaurando…' : 'Restaurar backup' }}
                                </button>
                            </div>
                        </template>
                        <p v-else class="text-sm text-muted-foreground">
                            Selecione uma sugestão na lista.
                        </p>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
