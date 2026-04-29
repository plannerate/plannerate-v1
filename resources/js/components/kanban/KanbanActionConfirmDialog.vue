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
import { useT } from '@/composables/useT';

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

const { t } = useT();

const currentContent = computed(() => (props.action ? {
    title: t(`app.kanban.confirm.${props.action}.title`),
    description: t(`app.kanban.confirm.${props.action}.description`),
    button: t(`app.kanban.actions.${props.action}`),
} : null));
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
                        <DialogTitle>{{ currentContent?.title ?? t('app.kanban.confirm.fallback_title') }}</DialogTitle>
                        <DialogDescription class="mt-1">
                            {{ currentContent?.description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-3">
                <div class="rounded-lg border bg-muted/30 p-3 text-sm">
                    <p class="font-medium text-foreground">
                        {{ gondolaName ?? t('app.kanban.card.unnamed_gondola') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('app.kanban.card.step') }}: {{ stepName ?? '-' }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="kanban-confirm-notes" class="text-xs font-medium text-muted-foreground">
                        {{ t('app.kanban.confirm.notes_label') }}
                    </label>
                    <textarea
                        id="kanban-confirm-notes"
                        v-model="notesModel"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        :placeholder="t('app.kanban.confirm.notes_placeholder')"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="busy" @click="emit('update:open', false)">
                    {{ t('app.kanban.actions.cancel') }}
                </Button>
                <Button :variant="isDestructive ? 'destructive' : 'default'" :disabled="busy" @click="emit('confirm')">
                    {{ busy ? t('app.kanban.actions.processing') : (currentContent?.button ?? t('app.kanban.actions.confirm')) }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
