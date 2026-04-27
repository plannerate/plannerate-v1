<script setup lang="ts">
import { CheckCircle2, Pause, Play, RotateCcw, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
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
}>();

const execution = computed(() => props.payload?.execution ?? null);
const allowedUsers = computed(() => props.payload?.allowed_users ?? []);
const currentStepId = computed(() => execution.value?.step?.id ?? null);
const currentStepIndex = computed(() => props.steps.findIndex((step) => step.id === currentStepId.value));

const notesModel = computed({
    get: () => props.actionNotes,
    set: (value: string) => emit('update:actionNotes', value),
});

const canStart = computed(() => execution.value?.can_start ?? false);
const canPause = computed(() => execution.value?.can_pause ?? false);
const canResume = computed(() => execution.value?.can_resume ?? false);
const canComplete = computed(() => execution.value?.can_complete ?? false);
const canAbandon = computed(() => execution.value?.can_abandon ?? false);

const statusLabels: Record<string, string> = {
    pending: 'Pendente',
    active: 'Em andamento',
    paused: 'Pausado',
    completed: 'Concluído',
    cancelled: 'Abandonado',
};

const actionLabels: Record<string, string> = {
    started: 'Iniciou',
    moved: 'Moveu',
    paused: 'Pausou',
    resumed: 'Retomou',
    assigned: 'Atribuiu',
    completed: 'Concluiu',
    cancelled: 'Abandonou',
    restored: 'Restaurou',
};

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
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ execution?.gondola?.name ?? 'Detalhes da execução' }}</DialogTitle>
                <DialogDescription>
                    Acompanhe a etapa, executantes, histórico e notas desta execução.
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
                            <h3 class="text-sm font-semibold text-foreground">Responsabilidade</h3>
                            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <p class="text-xs text-muted-foreground">Responsável atual</p>
                                    <p class="font-medium text-foreground">
                                        {{ execution.assigned_to_user?.name ?? '-' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">Iniciado por</p>
                                    <p class="font-medium text-foreground">
                                        {{ execution.started_by?.name ?? '-' }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-lg border bg-card p-3">
                            <h3 class="text-sm font-semibold text-foreground">Resumo</h3>
                            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <p class="text-xs text-muted-foreground">Status</p>
                                    <p class="font-medium text-foreground">{{ statusLabels[execution.status] }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">SLA</p>
                                    <p class="font-medium text-foreground">{{ formatDateTime(execution.sla_date) }}</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="rounded-lg border bg-card p-3">
                        <h3 class="text-sm font-semibold text-foreground">Fluxo do Workflow</h3>
                        <div class="relative mt-4 overflow-x-auto pb-1">
                            <div class="absolute left-0 right-0 top-5 h-0.5 min-w-[640px] bg-border" />
                            <div class="relative flex min-w-[640px] justify-between">
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
                        <h3 class="text-sm font-semibold text-foreground">Possíveis executantes</h3>
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
                                <span v-if="user.id === currentUserId" class="ml-1">(você)</span>
                            </span>
                            <span v-if="allowedUsers.length === 0" class="text-xs text-muted-foreground">
                                Nenhum usuário configurado para esta etapa.
                            </span>
                        </div>
                    </section>

                    <section class="rounded-lg border bg-card p-3">
                        <h3 class="text-sm font-semibold text-foreground">Histórico</h3>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="history in histories.slice(0, 5)"
                                :key="history.id"
                                class="rounded-md border bg-background p-2 text-xs"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-foreground">
                                        {{ actionLabels[history.action] ?? history.action }}
                                    </span>
                                    <span class="text-muted-foreground">{{ formatDateTime(history.performed_at) }}</span>
                                </div>
                                <p class="mt-0.5 text-muted-foreground">
                                    {{ history.performed_by?.name ?? 'Sistema' }}
                                    <span v-if="history.description">- {{ history.description }}</span>
                                </p>
                            </div>
                            <p v-if="histories.length === 0" class="text-xs text-muted-foreground">
                                Nenhum histórico registrado.
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <div class="space-y-2 border-t pt-3">
                <label for="kanban-action-notes" class="text-xs font-medium text-muted-foreground">Notas</label>
                <textarea
                    id="kanban-action-notes"
                    v-model="notesModel"
                    rows="2"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="Adicionar notas para a próxima ação..."
                />
            </div>

            <DialogFooter class="flex-wrap gap-2 sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <Button v-if="canStart" :disabled="busy" @click="emit('start')">
                        <Play class="size-4" />
                        Iniciar
                    </Button>
                    <Button v-if="canPause" variant="outline" :disabled="busy" @click="emit('pause')">
                        <Pause class="size-4" />
                        Pausar
                    </Button>
                    <Button v-if="canResume" variant="outline" :disabled="busy" @click="emit('resume')">
                        <RotateCcw class="size-4" />
                        Retomar
                    </Button>
                    <Button v-if="canComplete" variant="outline" :disabled="busy" @click="emit('complete')">
                        <CheckCircle2 class="size-4" />
                        Concluir
                    </Button>
                    <Button v-if="canAbandon" variant="destructive" :disabled="busy" @click="emit('abandon')">
                        <XCircle class="size-4" />
                        Abandonar
                    </Button>
                </div>
                <Button variant="outline" @click="emit('update:open', false)">Fechar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
