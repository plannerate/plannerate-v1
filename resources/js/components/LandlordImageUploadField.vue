<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ImageIcon, Link, Loader2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import EanReferenceController from '@/actions/App/Http/Controllers/Landlord/EanReferenceController';
import { Label } from '@/components/ui/label';

const props = withDefaults(defineProps<{
    name: string;
    label: string;
    initialUrl?: string | null;
    initialPath?: string | null;
    accept?: string;
    maxSizeMb?: number;
}>(), {
    initialUrl: null,
    initialPath: null,
    accept: 'image/*',
    maxSizeMb: 10,
});

const emit = defineEmits<{
    uploaded: [path: string];
    error: [message: string];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string>(props.initialUrl ?? '');
const storedPath = ref<string>(props.initialPath ?? '');
const isUploading = ref(false);
const isDragging = ref(false);
const errorMessage = ref('');
const urlInput = ref('');
const isImportingUrl = ref(false);

const uploadHttp = useHttp<{ file: File | null }, { path?: string; public_url?: string }>({ file: null });

function openPicker(): void {
    fileInput.value?.click();
}

function clearImage(event: MouseEvent): void {
    event.stopPropagation();
    previewUrl.value = '';
    storedPath.value = '';
    errorMessage.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

async function uploadFile(file: File): Promise<void> {
    if (file.size > props.maxSizeMb * 1024 * 1024) {
        errorMessage.value = `Arquivo muito grande. Máximo: ${props.maxSizeMb}MB`;
        emit('error', errorMessage.value);
        return;
    }

    previewUrl.value = URL.createObjectURL(file);
    isUploading.value = true;
    errorMessage.value = '';

    try {
        uploadHttp.file = file;

        const payload = await uploadHttp.submit({
            url: EanReferenceController.uploadImage.url(),
            method: 'post',
        });

        if (typeof payload.path !== 'string') {
            throw new Error('Upload falhou');
        }

        storedPath.value = payload.path;

        if (typeof payload.public_url === 'string') {
            previewUrl.value = payload.public_url;
        }

        emit('uploaded', payload.path);
    } catch {
        errorMessage.value = 'Falha ao enviar imagem. Tente novamente.';
        emit('error', errorMessage.value);
        previewUrl.value = props.initialUrl ?? '';
        storedPath.value = props.initialPath ?? '';
    } finally {
        isUploading.value = false;
    }
}

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const [file] = target.files ?? [];
    if (file) {
        void uploadFile(file);
    }
}

function onDragOver(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = true;
}

function onDragLeave(): void {
    isDragging.value = false;
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = false;
    const [file] = event.dataTransfer?.files ?? [];
    if (file && file.type.startsWith('image/')) {
        void uploadFile(file);
    }
}

async function importFromUrl(): Promise<void> {
    const url = urlInput.value.trim();
    if (!url || isImportingUrl.value || isUploading.value) {
        return;
    }

    try {
        new URL(url);
    } catch {
        errorMessage.value = 'URL inválida.';
        return;
    }

    isImportingUrl.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error('Falha ao buscar imagem.');
        }
        const blob = await response.blob();
        if (!blob.type.startsWith('image/')) {
            throw new Error('URL não aponta para uma imagem.');
        }
        const ext = blob.type.split('/')[1] ?? 'jpg';
        const file = new File([blob], `imported.${ext}`, { type: blob.type });
        urlInput.value = '';
        await uploadFile(file);
    } catch (err) {
        errorMessage.value = err instanceof Error ? err.message : 'Falha ao importar imagem.';
        emit('error', errorMessage.value);
    } finally {
        isImportingUrl.value = false;
    }
}
</script>

<template>
    <div class="space-y-2">
        <Label>{{ label }}</Label>

        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            class="sr-only"
            @change="onFileChange"
        />
        <input type="hidden" :name="name" :value="storedPath" />

        <!-- Drop / preview area — click opens picker -->
        <div
            class="relative flex aspect-square w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-border bg-muted/30 transition-colors"
            :class="{
                'border-primary/60 bg-primary/5': isDragging,
                'border-solid border-border hover:border-primary/40': previewUrl && !isDragging,
                'hover:border-primary/40': !previewUrl && !isDragging,
            }"
            @click="openPicker"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
        >
            <img
                v-if="previewUrl && !isUploading"
                :src="previewUrl"
                :alt="label"
                class="h-full w-full object-contain p-1"
            />
            <div v-else-if="isUploading" class="flex flex-col items-center gap-2 text-muted-foreground">
                <Loader2 class="size-8 animate-spin" />
                <span class="text-xs">Enviando...</span>
            </div>
            <div v-else-if="isDragging" class="flex flex-col items-center gap-2 text-primary">
                <ImageIcon class="size-10" />
                <span class="text-xs font-medium">Solte aqui</span>
            </div>
            <div v-else class="flex flex-col items-center gap-2 text-muted-foreground/50">
                <ImageIcon class="size-10" />
                <span class="text-xs">Clique ou arraste</span>
            </div>

            <!-- Clear button -->
            <button
                v-if="previewUrl && !isUploading"
                type="button"
                class="absolute right-1 top-1 rounded-full bg-background/90 p-0.5 text-muted-foreground shadow-sm transition hover:bg-destructive/10 hover:text-destructive"
                title="Remover imagem"
                @click="clearImage"
            >
                <X class="size-3.5" />
            </button>
        </div>

        <!-- URL import -->
        <div class="flex gap-1.5">
            <input
                v-model="urlInput"
                type="url"
                placeholder="https://..."
                class="h-8 min-w-0 flex-1 rounded-md border border-input bg-background px-2.5 text-xs text-foreground outline-none transition placeholder:text-muted-foreground/50 focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                @keydown.enter.prevent="importFromUrl"
            />
            <button
                type="button"
                :disabled="isImportingUrl || isUploading || !urlInput.trim()"
                class="inline-flex items-center gap-1 rounded-md border border-border bg-background px-2.5 py-1 text-xs font-medium text-foreground transition hover:bg-muted disabled:opacity-40"
                @click="importFromUrl"
            >
                <Loader2 v-if="isImportingUrl" class="size-3 animate-spin" />
                <Link v-else class="size-3" />
                Importar
            </button>
        </div>

        <p v-if="errorMessage" class="text-xs text-destructive">{{ errorMessage }}</p>
    </div>
</template>
