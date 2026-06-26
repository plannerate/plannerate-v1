<script setup lang="ts">
import { Camera, AlertTriangle, CheckCircle2, Clock, User as UserIcon, Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { ExecutionPayload } from './types';

/**
 * Barra-resumo da execução em loja (export §8): status, responsável, início,
 * SLA, evidências X/Y e divergências — apenas resumo + as 3 ações. Os detalhes
 * vivem nos modais.
 */
const props = defineProps<{
    execution: ExecutionPayload | null;
    loading: boolean;
}>();

const emit = defineEmits<{
    (e: 'add-evidence'): void;
    (e: 'add-divergence'): void;
    (e: 'complete'): void;
}>();

const { t } = useT();

/** Rótulo traduzido do status da execução. */
const statusLabel = computed(() =>
    props.execution?.status
        ? t(`app.kanban.executions.status.${props.execution.status}`)
        : '—',
);

/** Texto do SLA: dias restantes ou "vencido". */
const slaLabel = computed(() => {
    const days = props.execution?.sla_days_remaining;
    if (days === null || days === undefined) {
        return t('plannerate.execution.bar.sla_none');
    }
    if (days < 0) {
        return t('plannerate.execution.bar.sla_overdue', { days: String(Math.abs(days)) });
    }
    return t('plannerate.execution.bar.sla_remaining', { days: String(days) });
});

const slaOverdue = computed(() => (props.execution?.sla_days_remaining ?? 0) < 0);

/** Data de início formatada (pt-BR), quando disponível. */
const startedAtLabel = computed(() => {
    if (!props.execution?.started_at) {
        return '—';
    }
    return new Date(props.execution.started_at).toLocaleString('pt-BR');
});
</script>

<template>
    <div
        class="fixed inset-x-0 bottom-0 z-[600] border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] backdrop-blur"
    >
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2">
            <!-- Resumo -->
            <div v-if="loading" class="flex items-center gap-2 text-sm text-slate-500">
                <Loader2 class="size-4 animate-spin" />
                {{ t('plannerate.execution.bar.loading') }}
            </div>

            <template v-else>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                        {{ t('plannerate.execution.bar.status') }}
                    </span>
                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                        {{ statusLabel }}
                    </span>
                </div>

                <div class="flex items-center gap-1.5 text-sm text-slate-600">
                    <UserIcon class="size-4 text-slate-400" />
                    <span>{{ execution?.responsible ?? '—' }}</span>
                </div>

                <div class="flex items-center gap-1.5 text-sm text-slate-600">
                    <Clock class="size-4 text-slate-400" />
                    <span>{{ startedAtLabel }}</span>
                </div>

                <div
                    class="flex items-center gap-1.5 text-sm font-medium"
                    :class="slaOverdue ? 'text-red-600' : 'text-slate-600'"
                >
                    <Clock class="size-4" :class="slaOverdue ? 'text-red-500' : 'text-slate-400'" />
                    <span>{{ slaLabel }}</span>
                </div>

                <div class="flex items-center gap-1.5 text-sm">
                    <Camera class="size-4 text-slate-400" />
                    <span
                        class="font-semibold"
                        :class="execution?.evidence_summary.satisfied ? 'text-emerald-600' : 'text-amber-600'"
                    >
                        {{ execution?.evidence_summary.provided ?? 0 }}/{{ execution?.evidence_summary.required ?? 0 }}
                    </span>
                    <span class="text-slate-400">{{ t('plannerate.execution.bar.evidences') }}</span>
                </div>

                <div class="flex items-center gap-1.5 text-sm">
                    <AlertTriangle
                        class="size-4"
                        :class="(execution?.pending_divergences_count ?? 0) > 0 ? 'text-red-500' : 'text-slate-400'"
                    />
                    <span class="font-semibold text-slate-700">{{ execution?.divergences.length ?? 0 }}</span>
                    <span class="text-slate-400">{{ t('plannerate.execution.bar.divergences') }}</span>
                </div>
            </template>

            <!-- Ações -->
            <div class="ml-auto flex items-center gap-2">
                <Button variant="outline" size="sm" :disabled="loading" @click="emit('add-evidence')">
                    <Camera class="mr-1.5 size-4" />
                    {{ t('plannerate.execution.actions.add_evidence') }}
                </Button>
                <Button variant="outline" size="sm" :disabled="loading" @click="emit('add-divergence')">
                    <AlertTriangle class="mr-1.5 size-4" />
                    {{ t('plannerate.execution.actions.add_divergence') }}
                </Button>
                <Button size="sm" :disabled="loading" @click="emit('complete')">
                    <CheckCircle2 class="mr-1.5 size-4" />
                    {{ t('plannerate.execution.actions.complete') }}
                </Button>
            </div>
        </div>
    </div>
</template>
