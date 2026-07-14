<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UploadCloud, X, Loader2, Camera, LayoutGrid, Package, CircleCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/composables/useT';
import { executionRoutes } from '../routes';
import type { EvidenceSummary, ExecutionEvidence } from '../types';
import type { StructureOption } from '../useExecutionStructure';

/**
 * Modal "Adicionar evidência" (export §13–§15, mockup 3.png): tipo em cards com
 * ícone, módulo (obrigatório para Foto geral e Módulo), upload múltiplo com
 * miniaturas/remoção, observação com contador e indicador de progresso X/Y.
 */
const props = defineProps<{
    open: boolean;
    executionId: string;
    summary: EvidenceSummary | null;
    evidences: ExecutionEvidence[];
    modules: StructureOption[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { t } = useT();

/** Tipos de evidência com ícone e descrição (mockup 3.png). */
const evidenceTypes = [
    { value: 'general_photo', icon: Camera },
    { value: 'module', icon: LayoutGrid },
    { value: 'product', icon: Package },
] as const;

const NOTES_MAX = 300;
const MAX_FILES = 10;
const MAX_SIZE_MB = 10;

const type = ref<string>('general_photo');
const moduleValue = ref<string>('');
const notes = ref('');
const files = ref<File[]>([]);
const isDragging = ref(false);
const uploading = ref(false);
const uploadedCount = ref(0);

// Módulo só faz sentido para Módulo (obrigatório) e Produto (opcional);
// Foto geral é a gôndola inteira, então não exibe módulo.
const moduleRequired = computed(() => type.value === 'module');
const moduleVisible = computed(() => type.value === 'module' || type.value === 'product');

const previews = computed(() =>
    files.value.map((file) => ({ name: file.name, url: URL.createObjectURL(file) })),
);

const remaining = computed(() =>
    Math.max(0, (props.summary?.required ?? 0) - (props.summary?.provided ?? 0)),
);

const canSave = computed(
    () => files.value.length > 0 && !uploading.value && (!moduleRequired.value || !!moduleValue.value),
);

function addFiles(incoming: FileList | null): void {
    if (!incoming) {
        return;
    }

    for (const file of Array.from(incoming)) {
        if (files.value.length >= MAX_FILES) {
            break;
        }

        if (file.type.startsWith('image/') && file.size <= MAX_SIZE_MB * 1024 * 1024) {
            files.value.push(file);
        }
    }
}

function onFileInput(event: Event): void {
    addFiles((event.target as HTMLInputElement).files);
    (event.target as HTMLInputElement).value = '';
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
}

function removeFile(index: number): void {
    files.value.splice(index, 1);
}

/** Remove uma evidência já salva (recarrega apenas o payload da execução). */
function removeSavedEvidence(evidenceId: string): void {
    router.delete(executionRoutes.evidenceDestroy(props.executionId, evidenceId), {
        preserveScroll: true,
        preserveState: true,
        only: ['execution'],
    });
}

/** Rótulo legível do módulo selecionado (gravado em module_label). */
function selectedModuleLabel(): string | null {
    return props.modules.find((option) => option.value === moduleValue.value)?.label ?? null;
}

/** Envia os arquivos sequencialmente, atualizando o progresso. */
function uploadNext(index: number): void {
    if (index >= files.value.length) {
        uploading.value = false;
        close();

        return;
    }

    uploadedCount.value = index + 1;

    router.post(
        executionRoutes.evidenceStore(props.executionId),
        {
            type: type.value,
            module_label: moduleVisible.value ? selectedModuleLabel() : null,
            notes: notes.value || null,
            file: files.value[index],
        },
        {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            only: ['execution'],
            onSuccess: () => uploadNext(index + 1),
            onError: () => {
                uploading.value = false;
            },
        },
    );
}

function save(): void {
    if (!canSave.value) {
        return;
    }

    uploading.value = true;
    uploadedCount.value = 0;
    uploadNext(0);
}

function close(): void {
    if (uploading.value) {
        return;
    }

    files.value = [];
    moduleValue.value = '';
    notes.value = '';
    type.value = 'general_photo';
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (value ? null : close())">
        <DialogContent class="force-light z-[1000] flex max-h-[92vh] flex-col sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.execution.evidence.title') }}</DialogTitle>
                <DialogDescription>{{ t('plannerate.execution.evidence.description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto pr-1">
                <!-- Tipo (cards) -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.evidence.type') }}</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="option in evidenceTypes"
                            :key="option.value"
                            type="button"
                            class="flex flex-col items-center gap-1 rounded-lg border p-3 text-center transition"
                            :class="type === option.value
                                ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                            @click="type = option.value"
                        >
                            <component :is="option.icon" class="size-5" />
                            <span class="text-xs font-medium">{{ t(`plannerate.execution.evidence.types.${option.value}`) }}</span>
                            <span class="text-[10px] leading-tight text-slate-400">
                                {{ t(`plannerate.execution.evidence.type_hints.${option.value}`) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Módulo -->
                <div v-if="moduleVisible" class="space-y-1">
                    <label class="text-xs font-medium text-slate-600">
                        {{ t('plannerate.execution.evidence.module') }}
                        <span v-if="moduleRequired" class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="moduleValue"
                        class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-500/60 focus:ring-2 focus:ring-emerald-500/20"
                    >
                        <option value="">{{ t('plannerate.execution.evidence.select_module') }}</option>
                        <option v-for="option in modules" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <!-- Drop zone -->
                <div
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-center transition-colors"
                    :class="isDragging ? 'border-emerald-500/60 bg-emerald-50' : 'border-slate-300 hover:border-emerald-400/50'"
                    @click="($refs.fileInput as HTMLInputElement).click()"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop="onDrop"
                >
                    <UploadCloud class="size-8 text-slate-400" />
                    <p class="text-sm text-slate-600">{{ t('plannerate.execution.evidence.drop_hint') }}</p>
                    <p class="text-xs text-slate-400">{{ t('plannerate.execution.evidence.limits') }}</p>
                    <input ref="fileInput" type="file" accept="image/*" multiple class="sr-only" @change="onFileInput" />
                </div>

                <!-- Evidências já enviadas (carregadas do servidor) -->
                <div v-if="evidences.length" class="space-y-1.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ t('plannerate.execution.evidence.already_sent', { count: String(evidences.length) }) }}
                    </p>
                    <div class="grid grid-cols-4 gap-3">
                        <div v-for="evidence in evidences" :key="evidence.id" class="group relative">
                            <img
                                v-if="evidence.file_url"
                                :src="evidence.file_url"
                                :alt="evidence.file_name ?? ''"
                                class="aspect-square w-full rounded-lg object-cover ring-1 ring-slate-200"
                            />
                            <span
                                class="absolute bottom-1 left-1 rounded bg-black/60 px-1 text-[9px] font-medium text-white"
                            >
                                {{ t(`plannerate.execution.evidence.types.${evidence.type}`) }}
                            </span>
                            <button
                                type="button"
                                class="absolute -right-1.5 -top-1.5 rounded-full bg-red-500 p-0.5 text-white opacity-0 transition group-hover:opacity-100"
                                @click.stop="removeSavedEvidence(evidence.id)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Fotos adicionadas (lote atual) -->
                <div v-if="previews.length" class="space-y-1.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ t('plannerate.execution.evidence.added', { count: String(previews.length), max: String(MAX_FILES) }) }}
                    </p>
                    <div class="grid grid-cols-4 gap-3">
                        <div v-for="(preview, index) in previews" :key="preview.url" class="group relative">
                            <img :src="preview.url" :alt="preview.name" class="aspect-square w-full rounded-lg object-cover ring-1 ring-slate-200" />
                            <button
                                type="button"
                                class="absolute -right-1.5 -top-1.5 rounded-full bg-red-500 p-0.5 text-white opacity-0 transition group-hover:opacity-100"
                                @click.stop="removeFile(index)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Observação + contador -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.evidence.notes') }}</label>
                        <span class="text-[10px] text-slate-400">{{ notes.length }}/{{ NOTES_MAX }}</span>
                    </div>
                    <Textarea v-model="notes" rows="2" :maxlength="NOTES_MAX" :placeholder="t('plannerate.execution.evidence.notes_placeholder')" />
                </div>
            </div>

            <!-- Progresso -->
            <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                <CircleCheck class="size-5" :class="summary?.satisfied ? 'text-emerald-500' : 'text-amber-500'" />
                <div class="leading-tight">
                    <p class="font-medium text-slate-700">
                        {{ t('plannerate.execution.evidence.progress', {
                            provided: String(summary?.provided ?? 0),
                            required: String(summary?.required ?? 0),
                        }) }}
                    </p>
                    <p v-if="remaining > 0" class="text-xs text-slate-500">
                        {{ t('plannerate.execution.evidence.remaining', { count: String(remaining) }) }}
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <p v-if="uploading" class="mr-auto flex items-center gap-2 text-sm text-slate-500">
                    <Loader2 class="size-4 animate-spin" />
                    {{ t('plannerate.execution.evidence.uploading', { current: String(uploadedCount), total: String(files.length) }) }}
                </p>
                <Button variant="outline" :disabled="uploading" @click="close">
                    {{ t('plannerate.execution.common.cancel') }}
                </Button>
                <Button class="bg-emerald-600 hover:bg-emerald-700" :disabled="!canSave" @click="save">
                    {{ t('plannerate.execution.evidence.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
