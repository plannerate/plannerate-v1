<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UploadCloud, X, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/composables/useT';
import { executionRoutes } from '../routes';
import type { EvidenceSummary } from '../types';

/**
 * Modal "Adicionar evidência" (export §13–§15): tipo, upload múltiplo com
 * miniaturas/remoção antes de salvar, observação opcional e progresso X/Y.
 * Cada arquivo gera uma evidência (uma requisição por arquivo).
 */
const props = defineProps<{
    open: boolean;
    executionId: string;
    summary: EvidenceSummary | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const { t } = useT();

/** Tipos de evidência (export §13). */
const evidenceTypes = ['general_photo', 'module', 'product', 'other'] as const;

const type = ref<string>('general_photo');
const moduleLabel = ref('');
const notes = ref('');
const files = ref<File[]>([]);
const isDragging = ref(false);
const uploading = ref(false);
const uploadedCount = ref(0);

/** Limites sugeridos (export §13): 10 MB/arquivo, 10 fotos/envio. */
const MAX_FILES = 10;
const MAX_SIZE_MB = 10;

const previews = computed(() =>
    files.value.map((file) => ({ name: file.name, url: URL.createObjectURL(file) })),
);

const canSave = computed(() => files.value.length > 0 && !uploading.value);

/** Adiciona arquivos válidos (imagem + tamanho), respeitando o limite. */
function addFiles(incoming: FileList | null): void {
    if (!incoming) {
        return;
    }
    for (const file of Array.from(incoming)) {
        if (files.value.length >= MAX_FILES) {
            break;
        }
        const isImage = file.type.startsWith('image/');
        const withinSize = file.size <= MAX_SIZE_MB * 1024 * 1024;
        if (isImage && withinSize) {
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

/** Envia os arquivos sequencialmente, atualizando o progresso. */
function uploadNext(index: number): void {
    if (index >= files.value.length) {
        uploading.value = false;
        emit('saved');
        close();
        return;
    }

    uploadedCount.value = index + 1;

    router.post(
        executionRoutes.evidenceStore(props.executionId),
        {
            type: type.value,
            module_label: moduleLabel.value || null,
            notes: notes.value || null,
            file: files.value[index],
        },
        {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
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
    moduleLabel.value = '';
    notes.value = '';
    type.value = 'general_photo';
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (value ? null : close())">
        <DialogContent class="force-light z-[1000] flex max-h-[90vh] flex-col sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.execution.evidence.title') }}</DialogTitle>
                <DialogDescription>{{ t('plannerate.execution.evidence.description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto">
                <!-- Tipo -->
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-600">
                        {{ t('plannerate.execution.evidence.type') }}
                    </label>
                    <select
                        v-model="type"
                        class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    >
                        <option v-for="evidenceType in evidenceTypes" :key="evidenceType" :value="evidenceType">
                            {{ t(`plannerate.execution.evidence.types.${evidenceType}`) }}
                        </option>
                    </select>
                </div>

                <!-- Módulo (opcional) -->
                <div v-if="type === 'module'" class="space-y-1">
                    <label class="text-xs font-medium text-slate-600">
                        {{ t('plannerate.execution.evidence.module') }}
                    </label>
                    <input
                        v-model="moduleLabel"
                        type="text"
                        class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    />
                </div>

                <!-- Drop zone -->
                <div
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-center transition-colors"
                    :class="isDragging ? 'border-primary/60 bg-primary/5' : 'border-slate-300 hover:border-primary/40'"
                    @click="($refs.fileInput as HTMLInputElement).click()"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop="onDrop"
                >
                    <UploadCloud class="size-8 text-slate-400" />
                    <p class="text-sm text-slate-600">{{ t('plannerate.execution.evidence.drop_hint') }}</p>
                    <p class="text-xs text-slate-400">{{ t('plannerate.execution.evidence.limits') }}</p>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        multiple
                        class="sr-only"
                        @change="onFileInput"
                    />
                </div>

                <!-- Miniaturas -->
                <div v-if="previews.length" class="grid grid-cols-4 gap-3">
                    <div v-for="(preview, index) in previews" :key="preview.url" class="group relative">
                        <img :src="preview.url" :alt="preview.name" class="aspect-square w-full rounded-lg object-cover" />
                        <button
                            type="button"
                            class="absolute -right-1.5 -top-1.5 rounded-full bg-red-500 p-0.5 text-white opacity-0 transition group-hover:opacity-100"
                            @click.stop="removeFile(index)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Observação -->
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-600">
                        {{ t('plannerate.execution.evidence.notes') }}
                    </label>
                    <Textarea v-model="notes" rows="2" />
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
                <Button :disabled="!canSave" @click="save">
                    {{ t('plannerate.execution.evidence.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
