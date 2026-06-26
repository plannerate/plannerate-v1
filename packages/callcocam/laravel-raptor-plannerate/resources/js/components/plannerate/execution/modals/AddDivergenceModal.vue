<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/composables/useT';
import { executionRoutes } from '../routes';
import type { ExecutionDivergence } from '../types';

/**
 * Modal "Apontar divergência" (export §16–§19): tipo, localização (módulo,
 * prateleira, posição), produto, observação e fotos opcionais; além da lista
 * de divergências já registradas com ações de tratativa (justificar/resolver).
 */
const props = defineProps<{
    open: boolean;
    executionId: string;
    divergences: ExecutionDivergence[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const { t } = useT();

/** Tipos de divergência (export §16). */
const divergenceTypes = [
    'ruptura',
    'divergente',
    'falta_espaco',
    'embalagem_diferente',
    'nao_localizado',
    'sem_cadastro',
    'quantidade_insuficiente',
    'outro',
] as const;

const type = ref<string>('ruptura');
const moduleLabel = ref('');
const shelfLabel = ref('');
const positionLabel = ref('');
const productId = ref('');
const notes = ref('');
const photos = ref<File[]>([]);
const saving = ref(false);

const canSave = computed(() => !!type.value && !saving.value);

function onPhotos(event: Event): void {
    const incoming = (event.target as HTMLInputElement).files;
    photos.value = incoming ? Array.from(incoming).slice(0, 10) : [];
}

/** Cor do badge conforme o estado da divergência. */
function statusClass(status: string | null): string {
    switch (status) {
        case 'resolvida':
            return 'bg-emerald-100 text-emerald-700';
        case 'justificada':
            return 'bg-blue-100 text-blue-700';
        case 'rejeitada':
            return 'bg-slate-200 text-slate-600';
        case 'em_analise':
            return 'bg-amber-100 text-amber-700';
        default:
            return 'bg-red-100 text-red-700';
    }
}

function save(): void {
    if (!canSave.value) {
        return;
    }
    saving.value = true;

    router.post(
        executionRoutes.divergenceStore(props.executionId),
        {
            type: type.value,
            module_label: moduleLabel.value || null,
            shelf_label: shelfLabel.value || null,
            position_label: positionLabel.value || null,
            product_id: productId.value || null,
            notes: notes.value || null,
            photos: photos.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                resetForm();
                emit('saved');
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

/** Atualiza o estado de uma divergência existente. */
function updateStatus(divergence: ExecutionDivergence, status: string): void {
    router.patch(
        executionRoutes.divergenceUpdate(props.executionId, divergence.id),
        { status },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => emit('saved'),
        },
    );
}

function resetForm(): void {
    type.value = 'ruptura';
    moduleLabel.value = '';
    shelfLabel.value = '';
    positionLabel.value = '';
    productId.value = '';
    notes.value = '';
    photos.value = [];
}

function close(): void {
    if (saving.value) {
        return;
    }
    resetForm();
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (value ? null : close())">
        <DialogContent class="force-light z-[1000] flex max-h-[90vh] flex-col sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.execution.divergence.title') }}</DialogTitle>
                <DialogDescription>{{ t('plannerate.execution.divergence.description') }}</DialogDescription>
            </DialogHeader>

            <div class="grid flex-1 gap-6 overflow-y-auto md:grid-cols-2">
                <!-- Formulário -->
                <div class="space-y-3">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t('plannerate.execution.divergence.type') }}
                        </label>
                        <select
                            v-model="type"
                            class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                        >
                            <option v-for="divergenceType in divergenceTypes" :key="divergenceType" :value="divergenceType">
                                {{ t(`plannerate.execution.divergence.types.${divergenceType}`) }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-slate-600">
                                {{ t('plannerate.execution.divergence.module') }}
                            </label>
                            <input v-model="moduleLabel" type="text" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm outline-none focus:border-primary/60" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-slate-600">
                                {{ t('plannerate.execution.divergence.shelf') }}
                            </label>
                            <input v-model="shelfLabel" type="text" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm outline-none focus:border-primary/60" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-slate-600">
                                {{ t('plannerate.execution.divergence.position') }}
                            </label>
                            <input v-model="positionLabel" type="text" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm outline-none focus:border-primary/60" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t('plannerate.execution.divergence.product') }}
                        </label>
                        <input v-model="productId" type="text" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-primary/60" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t('plannerate.execution.divergence.notes') }}
                        </label>
                        <Textarea v-model="notes" rows="2" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t('plannerate.execution.divergence.photos') }}
                        </label>
                        <input type="file" accept="image/*" multiple class="block w-full text-xs text-slate-500" @change="onPhotos" />
                    </div>

                    <Button class="w-full" :disabled="!canSave" @click="save">
                        <Loader2 v-if="saving" class="mr-1.5 size-4 animate-spin" />
                        {{ t('plannerate.execution.divergence.save') }}
                    </Button>
                </div>

                <!-- Lista de divergências (export §19) -->
                <div class="space-y-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ t('plannerate.execution.divergence.registered') }}
                    </h4>
                    <p v-if="!divergences.length" class="text-sm text-slate-400">
                        {{ t('plannerate.execution.divergence.empty') }}
                    </p>
                    <div
                        v-for="divergence in divergences"
                        :key="divergence.id"
                        class="rounded-lg border border-slate-200 p-3 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-slate-700">
                                {{ t(`plannerate.execution.divergence.types.${divergence.type}`) }}
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(divergence.status)">
                                {{ t(`plannerate.execution.divergence.status.${divergence.status}`) }}
                            </span>
                        </div>
                        <p v-if="divergence.notes" class="mt-1 text-xs text-slate-500">{{ divergence.notes }}</p>
                        <div
                            v-if="divergence.status === 'aberta' || divergence.status === 'em_analise'"
                            class="mt-2 flex gap-2"
                        >
                            <Button variant="outline" size="sm" @click="updateStatus(divergence, 'justificada')">
                                {{ t('plannerate.execution.divergence.justify') }}
                            </Button>
                            <Button variant="outline" size="sm" @click="updateStatus(divergence, 'resolvida')">
                                {{ t('plannerate.execution.divergence.resolve') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="saving" @click="close">
                    {{ t('plannerate.execution.common.close') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
