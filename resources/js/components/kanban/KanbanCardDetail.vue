<script setup lang="ts">
import { BellRing, CheckCircle2, ChevronDown, ExternalLink, Pause, Play, RotateCcw, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import { show as gondolaShare } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaShareController';
import type { BoardStep, ExecutionDetails, WorkflowHistory } from '@/components/kanban/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/composables/useT';
import { editor as tenantEditorPlanogramGondolas } from '@/routes/tenant/planograms/gondolas';

const props = defineProps<{
    open: boolean;
    loading: boolean;
    payload: ExecutionDetails | null;
    histories: WorkflowHistory[];
    error: string | null;
    actionNotes: string;
    busy: boolean;
    steps: BoardStep[];
    currentUserId: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:actionNotes': [value: string];
    start: [];
    pause: [];
    resume: [];
    complete: [];
    abandon: [];
    requestAbandonment: [];
}>();

const execution = computed(() => props.payload?.execution ?? null);
const allowedUsers = computed(() => props.payload?.allowed_users ?? []);
// Camada de Execução em Loja (evidências/divergências); só exibida quando há dados.
const executionLayer = computed(() => props.payload?.execution_layer ?? null);
const hasExecutionData = computed(
    () => !!executionLayer.value && (executionLayer.value.evidences.length > 0 || executionLayer.value.divergences.length > 0),
);

/** Cor do badge conforme o estado da divergência. */
function divergenceStatusClass(status: string | null): string {
    switch (status) {
        case 'resolvida':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'justificada':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
        case 'rejeitada':
            return 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
        case 'em_analise':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        default:
            return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    }
}
// BoardStep.id = WorkflowTemplate.id; execution.step.template_id também é WorkflowTemplate.id
const currentStepId = computed(() => execution.value?.step?.template_id ?? null);
const currentStepIndex = computed(() => props.steps.findIndex((step) => step.id === currentStepId.value));
const expandedHistoryId = ref<string | null>(null);
const showAllHistories = ref(false);
const { t } = useT();

const notesModel = computed({
    get: () => props.actionNotes,
    set: (value: string) => emit('update:actionNotes', value),
});

const canStart = computed(() => execution.value?.can_start ?? false);
const canResume = computed(() => execution.value?.can_resume ?? false);
const canComplete = computed(() => execution.value?.can_complete ?? false);
const isActive = computed(() => execution.value?.status === 'active');
const wasStartedByCurrentUser = computed(
    () => isActive.value && execution.value?.started_by?.id === props.currentUserId,
);
const canPause = computed(() => (execution.value?.can_pause ?? false) && wasStartedByCurrentUser.value);
const canAbandon = computed(() => (execution.value?.can_abandon ?? false) && wasStartedByCurrentUser.value);
const canRequestAbandonment = computed(
    () => (execution.value?.can_request_abandonment ?? false) && !wasStartedByCurrentUser.value,
);
const executionLinkHref = computed(() => {
    const gondolaId = execution.value?.gondola?.id;

    if (!isActive.value || !gondolaId || !execution.value?.started_by?.id || !props.currentUserId) {
        return null;
    }

    if (wasStartedByCurrentUser.value) {
        return tenantEditorPlanogramGondolas.url({
            record: gondolaId,
        });
    }

    return gondolaView.url(gondolaId);
});
const executionLinkLabel = computed(() => (
    wasStartedByCurrentUser.value
        ? t('app.kanban.links.open_gondola_editor')
        : t('app.kanban.links.view_pdf')
));

const isCompleted = computed(() => execution.value?.status === 'completed');
const gondolaId = computed(() => execution.value?.gondola?.id ?? null);

/** Visualizar PDF — disponível quando concluído */
const completedPdfHref = computed(() =>
    isCompleted.value && gondolaId.value ? gondolaView.url(gondolaId.value) : null,
);

/** Visualizar como Repositor — disponível quando concluído */
const completedShareHref = computed(() =>
    isCompleted.value && gondolaId.value
        ? gondolaShare.url({ gondolaId: gondolaId.value })
        : null,
);
const visibleHistories = computed(() => (showAllHistories.value ? props.histories : props.histories.slice(0, 5)));

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function actionLabel(action: WorkflowHistory['action']): string {
    return t(`app.kanban.history.actions.${action}`);
}

function statusLabel(status: string): string {
    return t(`app.kanban.executions.status.${status}`);
}

function stepLabel(stepId: string | null): string {
    if (!stepId) {
        return '-';
    }

    return props.steps.find((step) => step.id === stepId)?.name ?? stepId;
}

function toggleHistory(historyId: string): void {
    expandedHistoryId.value = expandedHistoryId.value === historyId ? null : historyId;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ execution?.gondola?.name ?? t('app.kanban.detail.title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('app.kanban.detail.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                <div v-if="loading" class="space-y-3 py-4">
                    <div class="h-4 w-3/4 animate-pulse rounded bg-muted" />
                    <div class="h-20 w-full animate-pulse rounded bg-muted" />
                    <div class="h-28 w-full animate-pulse rounded bg-muted" />
                </div>

                <div
                    v-else-if="error"
                    class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
                >
                    {{ error }}
                </div>

                <div v-else-if="execution" class="space-y-3 pb-2">
                    <div class="grid gap-3 md:grid-cols-2">
                        <section class="rounded-lg border bg-card p-3">
                            <h3 class="text-sm font-semibold text-foreground">{{ t('app.kanban.detail.responsibility') }}</h3>
                            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <p class="text-xs text-muted-foreground">{{ t('app.kanban.detail.current_responsible') }}</p>
                                    <p class="font-medium text-foreground">
                                        {{ execution.assigned_to_user?.name ?? '-' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">{{ t('app.kanban.detail.started_by') }}</p>
                                    <p class="font-medium text-foreground">
                                        {{ execution.started_by?.name ?? '-' }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <Button v-if="executionLinkHref" variant="outline" size="sm" as-child>
                                    <a :href="executionLinkHref" target="_blank" rel="noopener noreferrer">
                                        <ExternalLink class="size-4" />
                                        {{ executionLinkLabel }}
                                    </a>
                                </Button>
                                <Button v-if="completedShareHref" variant="outline" size="sm" as-child>
                                    <a :href="completedShareHref" target="_blank" rel="noopener noreferrer">
                                        <ExternalLink class="size-4" />
                                        {{ t('app.kanban.links.view_as_repositor') }}
                                    </a>
                                </Button>
                                <Button v-if="completedPdfHref" variant="outline" size="sm" as-child>
                                    <a :href="completedPdfHref" target="_blank" rel="noopener noreferrer">
                                        <ExternalLink class="size-4" />
                                        {{ t('app.kanban.links.view_pdf') }}
                                    </a>
                                </Button>
                            </div>
                        </section>

                        <section class="rounded-lg border bg-card p-3">
                            <h3 class="text-sm font-semibold text-foreground">{{ t('app.kanban.detail.summary') }}</h3>
                            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <p class="text-xs text-muted-foreground">{{ t('app.kanban.detail.status') }}</p>
                                    <p class="font-medium text-foreground">{{ statusLabel(execution.status) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">{{ t('app.kanban.card.sla') }}</p>
                                    <p class="font-medium text-foreground">{{ formatDateTime(execution.sla_date) }}</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="rounded-lg border bg-card p-3">
                        <h3 class="text-sm font-semibold text-foreground">{{ t('app.kanban.detail.workflow_flow') }}</h3>
                        <div class="relative mt-4 overflow-x-auto pb-1">
                            <div class="absolute left-0 right-0 top-5 h-0.5 min-w-2xl bg-border" />
                            <div class="relative flex min-w-2xl justify-between">
                                <div v-for="(step, index) in steps" :key="step.id" class="flex w-24 flex-col items-center">
                                    <div
                                        class="relative z-10 flex size-9 items-center justify-center rounded-full border-2 bg-card text-xs font-bold transition"
                                        :class="
                                            step.id === currentStepId
                                                ? 'border-primary bg-primary text-primary-foreground shadow ring-4 ring-primary/20'
                                                : index < currentStepIndex
                                                  ? 'border-primary/50 text-primary'
                                                  : 'border-muted-foreground/30 text-muted-foreground'
                                        "
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <p
                                        class="mt-2 line-clamp-2 text-center text-[11px] font-medium"
                                        :class="step.id === currentStepId ? 'text-primary' : 'text-muted-foreground'"
                                    >
                                        {{ step.name }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border bg-card p-3">
                        <h3 class="text-sm font-semibold text-foreground">{{ t('app.kanban.detail.allowed_users') }}</h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="user in allowedUsers"
                                :key="user.id"
                                class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium"
                                :class="
                                    user.id === currentUserId
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border bg-muted/50 text-muted-foreground'
                                "
                            >
                                {{ user.name }}
                                <span v-if="user.id === currentUserId" class="ml-1">({{ t('app.kanban.detail.you') }})</span>
                            </span>
                            <span v-if="allowedUsers.length === 0" class="text-xs text-muted-foreground">
                                {{ t('app.kanban.detail.no_allowed_users') }}
                            </span>
                        </div>
                    </section>

                    <!-- Execução em loja: evidências + divergências -->
                    <section v-if="hasExecutionData && executionLayer" class="rounded-lg border bg-card p-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-foreground">{{ t('app.kanban.detail.execution_section') }}</h3>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="font-medium" :class="executionLayer.evidence_summary.satisfied ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ t('plannerate.execution.bar.evidences') }}: {{ executionLayer.evidence_summary.provided }}/{{ executionLayer.evidence_summary.required }}
                                </span>
                                <span class="font-medium" :class="executionLayer.pending_divergences_count > 0 ? 'text-red-600' : 'text-muted-foreground'">
                                    {{ t('plannerate.execution.bar.divergences') }}: {{ executionLayer.divergences.length }}
                                </span>
                            </div>
                        </div>

                        <!-- Evidências (miniaturas) -->
                        <div v-if="executionLayer.evidences.length" class="mt-3 grid grid-cols-6 gap-2">
                            <a
                                v-for="evidence in executionLayer.evidences"
                                :key="evidence.id"
                                :href="evidence.file_url ?? '#'"
                                target="_blank"
                                rel="noopener"
                                class="block"
                                :title="evidence.notes ?? ''"
                            >
                                <img
                                    v-if="evidence.file_url"
                                    :src="evidence.file_url"
                                    :alt="evidence.file_name ?? ''"
                                    class="aspect-square w-full rounded-md object-cover ring-1 ring-border"
                                />
                            </a>
                        </div>

                        <!-- Divergências (lista) -->
                        <div v-if="executionLayer.divergences.length" class="mt-3 space-y-1.5">
                            <div
                                v-for="divergence in executionLayer.divergences"
                                :key="divergence.id"
                                class="flex items-center justify-between gap-2 rounded-md border border-border/70 px-2.5 py-1.5 text-xs"
                            >
                                <div class="min-w-0">
                                    <span class="font-medium text-foreground">
                                        {{ t(`plannerate.execution.divergence.types.${divergence.type}`) }}
                                    </span>
                                    <span v-if="divergence.notes" class="ml-1 truncate text-muted-foreground">— {{ divergence.notes }}</span>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="divergenceStatusClass(divergence.status)">
                                    {{ t(`plannerate.execution.divergence.status.${divergence.status}`) }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border bg-card p-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-foreground">{{ t('app.kanban.history.title') }}</h3>
                            <Button
                                v-if="histories.length > 5"
                                variant="ghost"
                                size="sm"
                                class="h-6 px-2 text-xs"
                                @click="showAllHistories = !showAllHistories"
                            >
                                {{ showAllHistories ? t('app.kanban.history.show_less') : t('app.kanban.history.show_all') }}
                            </Button>
                        </div>

                        <div class="relative mt-3 pl-4">
                            <div v-if="visibleHistories.length > 0" class="absolute bottom-3 left-1 top-2 w-px bg-border/80" />
                            <div
                                v-for="history in visibleHistories"
                                :key="history.id"
                                class="relative pb-2 last:pb-0"
                            >
                                <span class="absolute -left-[17px] top-2 size-2.5 rounded-full border border-primary bg-background ring-2 ring-background" />

                                <div class="rounded-md border border-border/70 bg-background/70 px-2.5 py-2 text-xs transition-colors hover:bg-muted/30">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-foreground">
                                                {{ actionLabel(history.action) }}
                                            </p>
                                            <p class="truncate text-muted-foreground">
                                                {{ history.performed_by?.name ?? t('app.kanban.history.system') }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 text-[11px] text-muted-foreground">{{ formatDateTime(history.performed_at) }}</span>
                                    </div>

                                    <p v-if="history.description" class="mt-1.5 line-clamp-2 text-muted-foreground">
                                        {{ history.description }}
                                    </p>

                                    <button
                                        type="button"
                                        class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline"
                                        @click="toggleHistory(history.id)"
                                    >
                                        {{ expandedHistoryId === history.id ? t('app.kanban.history.hide_details') : t('app.kanban.history.view_details') }}
                                        <ChevronDown
                                            class="size-3 transition-transform"
                                            :class="{ 'rotate-180': expandedHistoryId === history.id }"
                                        />
                                    </button>

                                    <div
                                        v-if="expandedHistoryId === history.id"
                                        class="mt-2 grid gap-x-3 gap-y-1 rounded-md bg-muted/40 p-2 text-[11px] text-muted-foreground sm:grid-cols-2"
                                    >
                                        <p>
                                            <span class="font-medium text-foreground">{{ t('app.kanban.history.from_step') }}:</span>
                                            {{ history.from_step_name ?? stepLabel(history.from_step_id) }}
                                        </p>
                                        <p>
                                            <span class="font-medium text-foreground">{{ t('app.kanban.history.to_step') }}:</span>
                                            {{ history.to_step_name ?? stepLabel(history.to_step_id) }}
                                        </p>
                                        <p>
                                            <span class="font-medium text-foreground">{{ t('app.kanban.history.previous_responsible') }}:</span>
                                            {{ history.previous_responsible_id ?? '-' }}
                                        </p>
                                        <p>
                                            <span class="font-medium text-foreground">{{ t('app.kanban.history.new_responsible') }}:</span>
                                            {{ history.new_responsible_id ?? '-' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p v-if="histories.length === 0" class="text-xs text-muted-foreground">
                                {{ t('app.kanban.history.empty') }}
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <div class="space-y-2 border-t pt-3">
                <label for="kanban-action-notes" class="text-xs font-medium text-muted-foreground">
                    {{ t('app.kanban.detail.notes') }}
                </label>
                <textarea
                    id="kanban-action-notes"
                    v-model="notesModel"
                    rows="2"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    :placeholder="t('app.kanban.detail.notes_placeholder')"
                />
            </div>

            <DialogFooter class="flex-wrap gap-2 sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <Button v-if="canStart" :disabled="busy" @click="emit('start')">
                        <Play class="size-4" />
                        {{ t('app.kanban.actions.start') }}
                    </Button>
                    <Button v-if="canPause" variant="outline" :disabled="busy" @click="emit('pause')">
                        <Pause class="size-4" />
                        {{ t('app.kanban.actions.pause') }}
                    </Button>
                    <Button v-if="canResume" variant="outline" :disabled="busy" @click="emit('resume')">
                        <RotateCcw class="size-4" />
                        {{ t('app.kanban.actions.resume') }}
                    </Button>
                    <Button v-if="canComplete" variant="outline" :disabled="busy" @click="emit('complete')">
                        <CheckCircle2 class="size-4" />
                        {{ t('app.kanban.actions.complete') }}
                    </Button>
                    <Button v-if="canAbandon" variant="destructive" :disabled="busy" @click="emit('abandon')">
                        <XCircle class="size-4" />
                        {{ t('app.kanban.actions.abandon') }}
                    </Button>
                    <Button
                        v-if="canRequestAbandonment"
                        variant="outline"
                        class="border-amber-500/40 bg-amber-500/10 text-amber-700 hover:bg-amber-500/15 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200"
                        :disabled="busy"
                        @click="emit('requestAbandonment')"
                    >
                        <BellRing class="size-4" />
                        {{ t('app.kanban.actions.request_abandonment') }}
                    </Button>
                </div>
                <Button variant="outline" @click="emit('update:open', false)">{{ t('app.kanban.actions.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
