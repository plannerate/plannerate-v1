<script setup lang="ts">
import { Play, AlertTriangle, CheckCircle2, Camera, Clock, CalendarClock, Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { ExecutionPayload } from './types';

/**
 * Resumo da execução em loja (export §8, mockup 1.png): status, início, SLA,
 * evidências X/Y com barra de progresso, divergências e as 3 ações.
 *
 * O conteúdo é teleportado para dentro da toolbar de print (`#execution-bar-info`
 * e `#execution-bar-actions`), formando uma única linha — sem barra própria nem
 * borda divisória. Detalhes ficam nos modais.
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

const statusLabel = computed(() =>
    props.execution?.status
        ? t(`app.kanban.executions.status.${props.execution.status}`)
        : '—',
);

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

const startedAtLabel = computed(() =>
    props.execution?.started_at ? new Date(props.execution.started_at).toLocaleString('pt-BR') : '—',
);

/** Percentual de evidências obrigatórias já enviadas (0–100). */
const evidenceProgress = computed(() => {
    const summary = props.execution?.evidence_summary;

    if (!summary || summary.required === 0) {
        return 100;
    }

    return Math.min(100, Math.round((summary.provided / summary.required) * 100));
});

const divergenceCount = computed(() => props.execution?.divergences.length ?? 0);
</script>

<template>
    <!-- Infos teleportadas para a esquerda da toolbar de print -->
    <Teleport to="#execution-bar-info">
        <div v-if="loading" class="flex items-center gap-2 text-sm text-slate-500">
            <Loader2 class="size-4 animate-spin" />
            {{ t('plannerate.execution.bar.loading') }}
        </div>

        <template v-else>
            <!-- Status -->
            <div class="flex items-center gap-2">
                <span class="flex size-8 items-center justify-center rounded-full bg-emerald-500 text-white">
                    <Play class="size-4" fill="currentColor" />
                </span>
                <div class="leading-tight">
                    <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                        {{ t('plannerate.execution.bar.stage') }}
                    </p>
                    <p class="text-sm font-semibold text-emerald-600">{{ statusLabel }}</p>
                </div>
            </div>

            <div class="h-8 w-px bg-slate-200"></div>

            <!-- Iniciado em (Responsável fica no cabeçalho do planograma) -->
            <div class="leading-tight">
                <p class="flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                    <CalendarClock class="size-3" /> {{ t('plannerate.execution.bar.started_at') }}
                </p>
                <p class="text-sm font-medium text-slate-700">{{ startedAtLabel }}</p>
            </div>

            <div class="h-8 w-px bg-slate-200"></div>

            <!-- SLA -->
            <div class="leading-tight">
                <p class="flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                    <Clock class="size-3" /> {{ t('plannerate.execution.bar.sla') }}
                </p>
                <p class="text-sm font-medium" :class="slaOverdue ? 'text-red-600' : 'text-slate-700'">
                    {{ slaLabel }}
                </p>
            </div>

            <div class="h-8 w-px bg-slate-200"></div>

            <!-- Evidências + progresso -->
            <div class="leading-tight">
                <p class="flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                    <Camera class="size-3" /> {{ t('plannerate.execution.bar.evidences') }}
                </p>
                <div class="flex items-center gap-2">
                    <span
                        class="text-sm font-semibold"
                        :class="execution?.evidence_summary.satisfied ? 'text-emerald-600' : 'text-amber-600'"
                    >
                        {{ execution?.evidence_summary.provided ?? 0 }}/{{ execution?.evidence_summary.required ?? 0 }}
                    </span>
                    <span class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200">
                        <span
                            class="block h-full rounded-full transition-all"
                            :class="execution?.evidence_summary.satisfied ? 'bg-emerald-500' : 'bg-amber-500'"
                            :style="{ width: `${evidenceProgress}%` }"
                        ></span>
                    </span>
                </div>
            </div>

            <div class="h-8 w-px bg-slate-200"></div>

            <!-- Divergências -->
            <div class="leading-tight">
                <p class="flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                    <AlertTriangle class="size-3" /> {{ t('plannerate.execution.bar.divergences') }}
                </p>
                <p class="text-sm font-medium" :class="divergenceCount > 0 ? 'text-red-600' : 'text-slate-700'">
                    {{ t('plannerate.execution.bar.divergences_count', { count: String(divergenceCount) }) }}
                </p>
            </div>
        </template>
    </Teleport>

    <!-- Ações teleportadas para a direita da toolbar de print -->
    <Teleport to="#execution-bar-actions">
        <Button variant="outline" size="sm" :disabled="loading" @click="emit('add-evidence')">
            <Camera class="mr-1.5 size-4" />
            {{ t('plannerate.execution.actions.add_evidence') }}
        </Button>
        <Button
            variant="outline"
            size="sm"
            class="border-amber-300 text-amber-700 hover:bg-amber-50"
            :disabled="loading"
            @click="emit('add-divergence')"
        >
            <AlertTriangle class="mr-1.5 size-4" />
            {{ t('plannerate.execution.actions.add_divergence') }}
        </Button>
        <Button size="sm" class="bg-emerald-600 hover:bg-emerald-700" :disabled="loading" @click="emit('complete')">
            <CheckCircle2 class="mr-1.5 size-4" />
            {{ t('plannerate.execution.actions.complete') }}
        </Button>
    </Teleport>
</template>
