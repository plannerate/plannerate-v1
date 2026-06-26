<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/composables/useT';
import { executionRoutes } from '../routes';
import type { ExecutionPayload } from '../types';

/**
 * Modal "Concluir execução" (export §20–§24): resumo + validações antes de
 * concluir. Bloqueia quando há evidência obrigatória faltando ou divergência
 * pendente, oferecendo atalhos para resolver. Concluir aplica a regra de
 * status `completed` (sem coluna "Concluídos").
 */
const props = defineProps<{
    open: boolean;
    execution: ExecutionPayload | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'completed'): void;
    (e: 'go-evidence'): void;
    (e: 'go-divergence'): void;
}>();

const { t } = useT();

const saving = ref(false);

const evidencesOk = computed(() => props.execution?.evidence_summary.satisfied ?? false);
const divergencesOk = computed(() => (props.execution?.pending_divergences_count ?? 0) === 0);
const canComplete = computed(() => props.execution?.can_complete ?? false);

function confirm(): void {
    if (!canComplete.value || !props.execution) {
        return;
    }
    saving.value = true;

    router.post(
        executionRoutes.complete(props.execution.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => emit('completed'),
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function close(): void {
    if (saving.value) {
        return;
    }
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (value ? null : close())">
        <DialogContent class="force-light z-[1000] sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.execution.complete.title') }}</DialogTitle>
                <DialogDescription>{{ t('plannerate.execution.complete.description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <!-- Validação: evidências -->
                <div class="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                    <div class="flex items-center gap-2 text-sm">
                        <CheckCircle2 v-if="evidencesOk" class="size-5 text-emerald-500" />
                        <XCircle v-else class="size-5 text-red-500" />
                        <span class="text-slate-700">
                            {{ t('plannerate.execution.complete.evidences', {
                                provided: String(execution?.evidence_summary.provided ?? 0),
                                required: String(execution?.evidence_summary.required ?? 0),
                            }) }}
                        </span>
                    </div>
                    <Button v-if="!evidencesOk" variant="outline" size="sm" @click="emit('go-evidence')">
                        {{ t('plannerate.execution.complete.add_evidence') }}
                    </Button>
                </div>

                <!-- Validação: divergências -->
                <div class="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                    <div class="flex items-center gap-2 text-sm">
                        <CheckCircle2 v-if="divergencesOk" class="size-5 text-emerald-500" />
                        <XCircle v-else class="size-5 text-red-500" />
                        <span class="text-slate-700">
                            {{ t('plannerate.execution.complete.divergences', {
                                count: String(execution?.pending_divergences_count ?? 0),
                            }) }}
                        </span>
                    </div>
                    <Button v-if="!divergencesOk" variant="outline" size="sm" @click="emit('go-divergence')">
                        {{ t('plannerate.execution.complete.resolve_divergence') }}
                    </Button>
                </div>

                <p class="text-xs text-slate-500">{{ t('plannerate.execution.complete.notice') }}</p>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="saving" @click="close">
                    {{ t('plannerate.execution.common.cancel') }}
                </Button>
                <Button :disabled="!canComplete || saving" @click="confirm">
                    <Loader2 v-if="saving" class="mr-1.5 size-4 animate-spin" />
                    {{ t('plannerate.execution.complete.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
