<script setup lang="ts">
import { AlertTriangle, CheckCircle2, Pause, Play, RotateCcw, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import type { KanbanExecutionAction } from '@/components/kanban/types';
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
    action: KanbanExecutionAction | null;
    gondolaName: string | null;
    stepName: string | null;
    notes: string;
    busy: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:notes': [value: string];
    confirm: [];
}>();

const contentByAction: Record<KanbanExecutionAction, { title: string; description: string; button: string }> = {
    start: {
        title: 'Iniciar execução?',
        description: 'Você será definido como responsável pela execução e esta ação ficará registrada no histórico.',
        button: 'Iniciar',
    },
    pause: {
        title: 'Pausar execução?',
        description: 'A execução ficará pausada até ser retomada e a pausa será registrada no histórico.',
        button: 'Pausar',
    },
    resume: {
        title: 'Retomar execução?',
        description: 'A execução voltará para o status em andamento e a retomada será registrada no histórico.',
        button: 'Retomar',
    },
    complete: {
        title: 'Concluir execução?',
        description: 'A execução será marcada como concluída. Essa ação ficará registrada no histórico.',
        button: 'Concluir',
    },
    abandon: {
        title: 'Abandonar execução?',
        description: 'A execução será marcada como abandonada. Use as notas para registrar o motivo.',
        button: 'Abandonar',
    },
};

const currentContent = computed(() => (props.action ? contentByAction[props.action] : null));
const isDestructive = computed(() => props.action === 'abandon');
const notesModel = computed({
    get: () => props.notes,
    set: (value: string) => emit('update:notes', value),
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full"
                        :class="isDestructive ? 'bg-destructive/10' : 'bg-primary/10'"
                    >
                        <XCircle v-if="action === 'abandon'" class="size-5 text-destructive" />
                        <Play v-else-if="action === 'start'" class="size-5 text-primary" />
                        <Pause v-else-if="action === 'pause'" class="size-5 text-primary" />
                        <RotateCcw v-else-if="action === 'resume'" class="size-5 text-primary" />
                        <CheckCircle2 v-else-if="action === 'complete'" class="size-5 text-primary" />
                        <AlertTriangle v-else class="size-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle>{{ currentContent?.title ?? 'Confirmar ação?' }}</DialogTitle>
                        <DialogDescription class="mt-1">
                            {{ currentContent?.description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-3">
                <div class="rounded-lg border bg-muted/30 p-3 text-sm">
                    <p class="font-medium text-foreground">{{ gondolaName ?? 'Gondola sem nome' }}</p>
                    <p class="text-xs text-muted-foreground">Etapa: {{ stepName ?? '-' }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="kanban-confirm-notes" class="text-xs font-medium text-muted-foreground">
                        Notas da ação
                    </label>
                    <textarea
                        id="kanban-confirm-notes"
                        v-model="notesModel"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        placeholder="Opcional: registre observações para o histórico..."
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="busy" @click="emit('update:open', false)">
                    Cancelar
                </Button>
                <Button :variant="isDestructive ? 'destructive' : 'default'" :disabled="busy" @click="emit('confirm')">
                    {{ busy ? 'Processando...' : (currentContent?.button ?? 'Confirmar') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
