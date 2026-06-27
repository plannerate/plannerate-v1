<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, Loader2, Camera, AlertTriangle, Clock, User as UserIcon, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/composables/useT';
import { executionRoutes } from '../routes';
import type { ExecutionPayload } from '../types';

/**
 * Modal "Concluir execução" (export §20–§24, mockup 4.png): resumo em 4 colunas,
 * checklist de validações e aviso. Bloqueia quando falta evidência obrigatória
 * ou há divergência pendente, com atalhos para resolver. Concluir aplica o
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

const COMMENT_MAX = 500;
const comment = ref('');
const saving = ref(false);

const evidencesOk = computed(() => props.execution?.evidence_summary.satisfied ?? false);
const divergencesOk = computed(() => (props.execution?.pending_divergences_count ?? 0) === 0);
// O botão reflete exatamente as validações visíveis (evidências + divergências).
// O backend continua sendo a autoridade final (valida de novo ao concluir).
const canComplete = computed(() => evidencesOk.value && divergencesOk.value);

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

function confirm(): void {
    if (!canComplete.value || !props.execution) {
        return;
    }
    saving.value = true;

    router.post(
        executionRoutes.complete(props.execution.id),
        { notes: comment.value || null },
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
        <DialogContent class="force-light z-[1000] flex max-h-[92vh] flex-col sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.execution.complete.title') }}</DialogTitle>
                <DialogDescription>{{ t('plannerate.execution.complete.description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto pr-1">
                <!-- Resumo em 4 colunas -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 p-3 text-center">
                        <Camera class="mx-auto size-4 text-slate-400" />
                        <p class="mt-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                            {{ t('plannerate.execution.complete.summary_evidences') }}
                        </p>
                        <p class="text-sm font-semibold text-slate-700">
                            {{ execution?.evidence_summary.provided ?? 0 }}/{{ execution?.evidence_summary.required ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 text-center">
                        <AlertTriangle class="mx-auto size-4 text-slate-400" />
                        <p class="mt-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                            {{ t('plannerate.execution.complete.summary_divergences') }}
                        </p>
                        <p class="text-sm font-semibold text-slate-700">{{ execution?.divergences.length ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 text-center">
                        <Clock class="mx-auto size-4 text-slate-400" />
                        <p class="mt-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                            {{ t('plannerate.execution.complete.summary_sla') }}
                        </p>
                        <p class="text-sm font-semibold text-slate-700">{{ slaLabel }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 text-center">
                        <UserIcon class="mx-auto size-4 text-slate-400" />
                        <p class="mt-1 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                            {{ t('plannerate.execution.complete.summary_responsible') }}
                        </p>
                        <p class="truncate text-sm font-semibold text-slate-700">{{ execution?.responsible ?? '—' }}</p>
                    </div>
                </div>

                <!-- Validações -->
                <div class="space-y-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ t('plannerate.execution.complete.validations') }}
                    </h4>

                    <div class="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                        <div class="flex items-start gap-2 text-sm">
                            <CheckCircle2 v-if="evidencesOk" class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                            <XCircle v-else class="mt-0.5 size-5 shrink-0 text-red-500" />
                            <div class="leading-tight">
                                <p class="font-medium text-slate-700">{{ t('plannerate.execution.complete.check_evidences') }}</p>
                                <p class="text-xs text-slate-500">{{ t('plannerate.execution.complete.check_evidences_hint') }}</p>
                            </div>
                        </div>
                        <Button v-if="!evidencesOk" variant="outline" size="sm" @click="emit('go-evidence')">
                            {{ t('plannerate.execution.complete.add_evidence') }}
                        </Button>
                    </div>

                    <div class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm">
                        <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                        <div class="leading-tight">
                            <p class="font-medium text-slate-700">{{ t('plannerate.execution.complete.check_notes') }}</p>
                            <p class="text-xs text-slate-500">{{ t('plannerate.execution.complete.check_notes_hint') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                        <div class="flex items-start gap-2 text-sm">
                            <CheckCircle2 v-if="divergencesOk" class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                            <XCircle v-else class="mt-0.5 size-5 shrink-0 text-red-500" />
                            <div class="leading-tight">
                                <p class="font-medium text-slate-700">{{ t('plannerate.execution.complete.check_divergences') }}</p>
                                <p class="text-xs text-slate-500">{{ t('plannerate.execution.complete.check_divergences_hint') }}</p>
                            </div>
                        </div>
                        <Button v-if="!divergencesOk" variant="outline" size="sm" @click="emit('go-divergence')">
                            {{ t('plannerate.execution.complete.resolve_divergence') }}
                        </Button>
                    </div>
                </div>

                <!-- Aviso -->
                <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{{ t('plannerate.execution.complete.notice') }}</span>
                </div>

                <!-- Comentário final -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.complete.comment') }}</label>
                        <span class="text-[10px] text-slate-400">{{ comment.length }}/{{ COMMENT_MAX }}</span>
                    </div>
                    <Textarea v-model="comment" rows="2" :maxlength="COMMENT_MAX" :placeholder="t('plannerate.execution.complete.comment_placeholder')" />
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="saving" @click="close">
                    {{ t('plannerate.execution.common.cancel') }}
                </Button>
                <Button class="bg-emerald-600 hover:bg-emerald-700" :disabled="!canComplete || saving" @click="confirm">
                    <Loader2 v-if="saving" class="mr-1.5 size-4 animate-spin" />
                    {{ t('plannerate.execution.complete.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
