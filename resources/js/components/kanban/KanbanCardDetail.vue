<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ExecutionDetails } from '@/components/kanban/types';
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
    error: string | null;
    assigning: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    assign: [userId: string];
}>();

const selectedUserId = ref('');

const execution = computed(() => props.payload?.execution ?? null);
const allowedUsers = computed(() => props.payload?.allowed_users ?? []);

watch(
    () => props.payload,
    (payload) => {
        selectedUserId.value = payload?.execution.assigned_to_user?.id ?? '';
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Detalhes da execução</DialogTitle>
                <DialogDescription>
                    Consulte a gondola, etapa atual e responsavel permitido para esta etapa.
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="space-y-3 py-4">
                <div class="h-4 w-3/4 animate-pulse rounded bg-muted" />
                <div class="h-4 w-1/2 animate-pulse rounded bg-muted" />
                <div class="h-9 w-full animate-pulse rounded bg-muted" />
            </div>

            <div
                v-else-if="error"
                class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
            >
                {{ error }}
            </div>

            <div v-else-if="execution" class="space-y-4 py-2">
                <div class="grid gap-3 text-sm">
                    <div>
                        <p class="text-xs text-muted-foreground">Gondola</p>
                        <p class="font-medium text-foreground">{{ execution.gondola?.name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Local</p>
                        <p class="font-medium text-foreground">{{ execution.gondola?.location ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Etapa</p>
                        <p class="font-medium text-foreground">{{ execution.step?.name ?? '-' }}</p>
                        <p v-if="execution.step?.description" class="text-xs text-muted-foreground">
                            {{ execution.step.description }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label for="kanban-assignee" class="text-xs font-medium text-foreground">
                        Responsavel
                    </label>
                    <select
                        id="kanban-assignee"
                        v-model="selectedUserId"
                        class="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Selecione um responsavel</option>
                        <option v-for="user in allowedUsers" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Fechar</Button>
                <Button :disabled="!selectedUserId || assigning || loading" @click="emit('assign', selectedUserId)">
                    Confirmar responsavel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
