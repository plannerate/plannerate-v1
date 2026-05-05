<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-3xl max-h-[90vh] flex flex-col">
            <DialogHeader>
                <DialogTitle>Upload de Imagem do Produto</DialogTitle>
                <DialogDescription>
                    Faça upload de uma imagem para o produto {{ product?.name }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4 overflow-y-auto">
                <!-- Preview da imagem atual -->
                <div v-if="product?.image_url && !selectedFile" class="flex flex-col items-center gap-2">
                    <Label class="text-xs text-muted-foreground">Imagem Atual</Label>
                    <img :src="product.image_url" :alt="product.name" class="h-32 w-32 rounded border object-contain" />
                </div>

                <Separator v-if="product?.image_url && !selectedFile" />

                <!-- Área de upload -->
                <div v-if="!selectedFile" class="space-y-2">
                    <Label for="image-upload">Nova Imagem</Label>
                    <div class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 transition-colors"
                        :class="{
                            'border-primary bg-primary/5': isDragging,
                            'border-border hover:border-primary/50 hover:bg-accent': !isDragging,
                        }" @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop">
                        <input id="image-upload" ref="fileInput" type="file" accept="image/*" class="hidden"
                            @change="handleFileSelect" />

                        <ImageIcon class="size-10 text-muted-foreground" />
                        <div class="space-y-1 text-center">
                            <p class="text-sm font-medium">
                                Arraste uma imagem ou clique para selecionar
                            </p>
                            <p class="text-xs text-muted-foreground">
                                PNG, JPG, GIF até 5MB
                            </p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="() => fileInput?.click()">
                            <Upload class="mr-2 size-4" />
                            Selecionar Arquivo
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <Label for="image-url-input">URL da imagem</Label>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch">
                            <Input
                                id="image-url-input"
                                v-model="imageUrlInput"
                                type="url"
                                class="sm:flex-1"
                                placeholder="https://exemplo.com/imagem.jpg"
                                @keydown.enter.prevent="importFromUrl"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                class="sm:w-auto sm:shrink-0"
                                :disabled="isImportingFromUrl || isUploading"
                                @click="importFromUrl"
                            >
                                <Loader2 v-if="isImportingFromUrl" class="mr-2 size-4 animate-spin" />
                                <span>{{ isImportingFromUrl ? 'Importando...' : 'Importar URL' }}</span>
                            </Button>
                        </div>
                    </div>

                    <p v-if="uploadError" class="text-xs text-destructive">
                        {{ uploadError }}
                    </p>
                </div>

                <!-- Editor de Crop -->
                <div v-else class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label>Ajustar Imagem</Label>
                        <Button type="button" variant="ghost" size="sm" @click="clearSelectedFile">
                            <X class="mr-2 size-4" />
                            Trocar Imagem
                        </Button>
                    </div>

                    <div class="rounded-lg border bg-muted/30 p-2">
                        <Cropper ref="cropperRef" class="h-96 w-full" :src="previewUrl ?? ''" />
                    </div>

                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <p class="truncate flex-1">{{ selectedFile.name }}</p>
                        <span>{{ formatFileSize(selectedFile.size) }}</span>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0">
                <Button 
                    v-if="product?.image_url && !selectedFile" 
                    type="button" 
                    variant="destructive" 
                    @click="handleDeleteImage"
                    :disabled="isUploading"
                >
                    <Trash2 class="mr-2 size-4" />
                    Remover Imagem
                </Button>
                <Button type="button" variant="outline" @click="handleClose" :disabled="isUploading">
                    Cancelar
                </Button>
                <Button type="button" @click="handleUploadImage" :disabled="!selectedFile || isUploading">
                    <Loader2 v-if="isUploading" class="mr-2 size-4 animate-spin" />
                    <Upload v-else class="mr-2 size-4" />
                    {{ isUploading ? 'Enviando...' : 'Upload' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImageIcon, Loader2, Trash2, Upload, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import { toast } from 'vue-sonner';
import { wayfinderPath } from '../../../../../libs/wayfinderPath';
import { uploadImage, deleteImage } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Api/ProductImageController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { Product } from '@/types/planogram';
import 'vue-advanced-cropper/dist/style.css';

interface Props {
    product: Product | null;
}

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { required: true });

// Referências
const cropperRef = ref<InstanceType<typeof Cropper> | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// Estado do upload de imagem
const selectedFile = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadError = ref<string | null>(null);
const imageUrlInput = ref('');
const isImportingFromUrl = ref(false);

// Limpa estado quando dialog fecha
watch(open, (newValue) => {
    if (!newValue) {
        clearSelectedFile();
    }
});

function handleFileSelect(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        validateAndSetFile(file);
    }
}

function handleDrop(event: DragEvent) {
    isDragging.value = false;
    const file = event.dataTransfer?.files[0];

    if (file) {
        validateAndSetFile(file);
    }
}

function validateAndSetFile(file: File) {
    uploadError.value = null;

    // Valida tipo de arquivo
    if (!file.type.startsWith('image/')) {
        uploadError.value = 'Por favor, selecione um arquivo de imagem válido';

        return;
    }

    // Valida tamanho (5MB)
    const maxSize = 5 * 1024 * 1024; // 5MB

    if (file.size > maxSize) {
        uploadError.value = 'A imagem deve ter no máximo 5MB';

        return;
    }

    selectedFile.value = file;

    // Cria preview
    const reader = new FileReader();
    reader.onload = (e) => {
        previewUrl.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
}

function clearSelectedFile() {
    selectedFile.value = null;
    previewUrl.value = null;
    uploadError.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

async function importFromUrl(): Promise<void> {
    const url = imageUrlInput.value.trim();

    if (url === '' || isImportingFromUrl.value || isUploading.value) {
        return;
    }

    try {
         
        new URL(url);
    } catch {
        uploadError.value = 'Informe uma URL válida.';

        return;
    }

    isImportingFromUrl.value = true;
    uploadError.value = null;

    try {
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error('Não foi possível importar a imagem pela URL.');
        }

        const blob = await response.blob();

        if (!blob.type.startsWith('image/')) {
            throw new Error('A URL informada não retorna uma imagem válida.');
        }

        const maxSize = 5 * 1024 * 1024;

        if (blob.size > maxSize) {
            throw new Error('A imagem deve ter no máximo 5MB');
        }

        const fileExtension = blob.type.split('/')[1] ?? 'jpg';
        const fileName = `imported-image.${fileExtension}`;
        const importedFile = new File([blob], fileName, { type: blob.type });

        validateAndSetFile(importedFile);
        imageUrlInput.value = '';
    } catch (error) {
        uploadError.value = error instanceof Error
            ? error.message
            : 'Não foi possível importar a imagem pela URL.';
    } finally {
        isImportingFromUrl.value = false;
    }
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function resolveUploadErrorMessage(rawMessage?: string): string {
    if (!rawMessage || rawMessage.trim() === '') {
        return 'Erro ao enviar imagem. Verifique o formato, o tamanho (ate 5MB) e tente novamente.';
    }

    if (rawMessage === 'Erro ao fazer upload da imagem.') {
        return 'Falha ao salvar a imagem no servidor. Verifique se o arquivo e uma imagem valida (ate 5MB) e tente novamente.';
    }

    return rawMessage;
}

function handleClose() {
    open.value = false;
}

async function handleUploadImage() {
    if (!selectedFile.value || !props.product?.id) {
        return;
    }

    isUploading.value = true;
    uploadError.value = null;

    try {
        // Obtém a imagem cortada do cropper
        const canvas = cropperRef.value?.getResult()?.canvas;

        let fileToUpload: File | Blob = selectedFile.value;

        // Se há canvas (imagem foi cortada), converte para blob
        if (canvas) {
            const blob = await new Promise<Blob>((resolve, reject) => {
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error('Falha ao converter imagem'));
                    }
                }, selectedFile.value?.type || 'image/png', 0.95);
            });

            // Cria um File a partir do Blob mantendo o nome original
            fileToUpload = new File([blob], selectedFile.value.name, {
                type: blob.type,
            });
        }

        const formData = new FormData();
        formData.append('image', fileToUpload);
        formData.append('product_id', props.product.id);

        // Faz upload via Inertia
        router.post(wayfinderPath(uploadImage.url({
            subdomain: window.location.hostname.split('.')[0],
            product: props.product.id,
        })), formData, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Imagem enviada com sucesso!');
                open.value = false;
                clearSelectedFile();

                // Recarrega a página para atualizar a imagem
                router.reload({ only: ['product'] });
            },
            onError: (errors) => {
                console.error('Erro ao enviar imagem:', errors);
                const pick = (key: 'product' | 'image'): string | undefined => {
                    const v = errors[key];

                    if (Array.isArray(v)) {
                        return v[0];
                    }

                    return typeof v === 'string' ? v : undefined;
                };
                const message = resolveUploadErrorMessage(
                    pick('product') ?? pick('image') ?? 'Erro ao enviar imagem. Tente novamente.',
                );
                uploadError.value = message;
                toast.error(message);
            },
            onFinish: () => {
                isUploading.value = false;
            },
        });
    } catch (error) {
        console.error('Erro ao fazer upload:', error);
        const message = resolveUploadErrorMessage(error instanceof Error ? error.message : undefined);
        uploadError.value = message;
        toast.error(message);
        isUploading.value = false;
    }
}

async function handleDeleteImage() {
    if (!props.product?.id) {
        return;
    }

    isUploading.value = true;
    uploadError.value = null;

    try {
        // Envia requisição para remover a imagem (marcar image_url como null)
        router.delete(wayfinderPath(deleteImage.url({
            subdomain: window.location.hostname.split('.')[0],
            product: props.product.id,
        })), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Imagem removida com sucesso!');
                open.value = false;

                // Recarrega a página para atualizar
                router.reload({ only: ['product'] });
            },
            onError: (errors) => {
                console.error('Erro ao remover imagem:', errors);
                const pick = (key: 'product' | 'image'): string | undefined => {
                    const v = errors[key];

                    if (Array.isArray(v)) {
                        return v[0];
                    }

                    return typeof v === 'string' ? v : undefined;
                };
                const message = pick('product') ?? pick('image') ?? 'Erro ao remover imagem. Tente novamente.';
                uploadError.value = message;
                toast.error(message);
            },
            onFinish: () => {
                isUploading.value = false;
            },
        });
    } catch (error) {
        console.error('Erro ao deletar imagem:', error);
        uploadError.value = 'Erro ao deletar imagem. Tente novamente.';
        toast.error('Erro ao deletar imagem');
        isUploading.value = false;
    }
}
</script>
