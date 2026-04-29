<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import imageRoutes from '@/routes/tenant/products/image';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

const props = withDefaults(defineProps<{
    subdomain: string;
    name: string;
    label: string;
    ean?: string | null;
    initialUrl?: string | null;
    initialPath?: string | null;
    accept?: string;
    maxSizeMb?: number;
    aiEnabled?: boolean;
}>(), {
    initialUrl: null,
    initialPath: null,
    accept: 'image/*',
    maxSizeMb: 10,
    aiEnabled: true,
});

const emit = defineEmits<{
    uploaded: [value: string];
    aiProcessed: [value: string];
    repositoryProcessed: [value: string];
    error: [value: string];
}>();

const { t } = useT();
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const previewUrl = ref<string>(props.initialUrl ?? '');
const storedPath = ref<string>(props.initialPath ?? '');
const isUploading = ref(false);
const isProcessingAi = ref(false);
const isFetchingRepository = ref(false);

const uploadHttp = useHttp<{ file: File | null }, { path?: string; public_url?: string }>({
    file: null,
});
const aiProcessHttp = useHttp<{ path: string }, { id?: string; status?: string }>({
    path: '',
});
const statusHttp = useHttp<{}, {
    status?: string;
    path?: string;
    public_url?: string;
    error_message?: string;
}>({});
const repositoryHttp = useHttp<{ ean: string }, { path?: string; public_url?: string }>({
    ean: '',
});

function toHttpRoute(route: { url: string; method: string }): UrlMethodPair {
    return {
        url: tenantWayfinderPath(route.url),
        method: route.method as UrlMethodPair['method'],
    };
}

function openPicker(): void {
    fileInput.value?.click();
}

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const [file] = target.files ?? [];

    if (!file) {
        return;
    }

    if (file.size > props.maxSizeMb * 1024 * 1024) {
        const message = t('app.tenant.products.form.image_upload.too_large', { size: props.maxSizeMb });
        emit('error', message);
        target.value = '';

        return;
    }

    selectedFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
    void uploadSelectedFile();
}

async function uploadSelectedFile(): Promise<void> {
    if (!selectedFile.value || isUploading.value) {
        return;
    }

    isUploading.value = true;

    try {
        uploadHttp.file = selectedFile.value;

        const payload = await uploadHttp.submit(
            toHttpRoute(imageRoutes.upload(props.subdomain))
        );

        if (typeof payload.path !== 'string') {
            throw new Error(t('app.tenant.products.form.image_upload.upload_failed'));
        }

        storedPath.value = payload.path;

        if (typeof payload.public_url === 'string') {
            previewUrl.value = payload.public_url;
        }

        emit('uploaded', payload.path);
    } catch {
        const message = t('app.tenant.products.form.image_upload.upload_failed');
        emit('error', message);
    } finally {
        isUploading.value = false;
    }
}

async function processWithAi(): Promise<void> {
    if (!props.aiEnabled || storedPath.value === '' || isProcessingAi.value) {
        return;
    }

    isProcessingAi.value = true;

    try {
        aiProcessHttp.path = storedPath.value;

        const payload = await aiProcessHttp.submit(
            toHttpRoute(imageRoutes.ai.process(props.subdomain))
        );

        if (typeof payload.id !== 'string') {
            throw new Error(t('app.tenant.products.form.image_ai.start_failed'));
        }

        await pollAiStatus(payload.id);
    } catch (error) {
        const message = error instanceof Error
            ? error.message
            : t('app.tenant.products.form.image_ai.start_failed');
        emit('error', message);
        isProcessingAi.value = false;
    }
}

async function fetchFromRepository(): Promise<void> {
    if (isFetchingRepository.value) {
        return;
    }

    const currentEan = (props.ean ?? '').trim();

    if (currentEan === '') {
        emit('error', t('app.tenant.products.form.image_repository.ean_required'));

        return;
    }

    isFetchingRepository.value = true;

    try {
        repositoryHttp.ean = currentEan;

        const payload = await repositoryHttp.submit(
            toHttpRoute(imageRoutes.repository.fetch(props.subdomain))
        );

        if (typeof payload.path !== 'string') {
            throw new Error(t('app.tenant.products.form.image_repository.not_found'));
        }

        storedPath.value = payload.path;

        if (typeof payload.public_url === 'string') {
            previewUrl.value = payload.public_url;
        }

        emit('repositoryProcessed', payload.path);
    } catch {
        emit('error', t('app.tenant.products.form.image_repository.fetch_failed'));
    } finally {
        isFetchingRepository.value = false;
    }
}

async function pollAiStatus(operationId: string): Promise<void> {
    let attempts = 0;

    while (attempts < 90) {
        attempts += 1;
        await new Promise((resolve) => setTimeout(resolve, 1500));

        const statusRoute = imageRoutes.ai.status({
            subdomain: props.subdomain,
            operation: operationId
        });
        const payload = await statusHttp.submit(toHttpRoute(statusRoute));

        if (payload.status === 'completed' && typeof payload.path === 'string') {
            storedPath.value = payload.path;

            if (typeof payload.public_url === 'string') {
                previewUrl.value = payload.public_url;
            }

            emit('aiProcessed', payload.path);
            isProcessingAi.value = false;

            return;
        }

        if (payload.status === 'failed') {
            throw new Error(payload.error_message ?? t('app.tenant.products.form.image_ai.failed'));
        }
    }

    throw new Error(t('app.tenant.products.form.image_ai.timeout'));
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <input :name="name" type="hidden" :value="storedPath" />

        <Label :for="`${name}-file`">{{ label }}</Label>

        <div class="rounded-lg border border-border bg-muted/10 p-3">
            <div class="mb-3 flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
                    :disabled="isUploading"
                    @click="openPicker"
                >
                    {{ isUploading ? t('app.loading') : t('app.tenant.products.form.image_upload.select') }}
                </button>

                <button
                    v-if="aiEnabled"
                    type="button"
                    class="inline-flex items-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isProcessingAi || storedPath === ''"
                    @click="processWithAi"
                >
                    {{ isProcessingAi ? t('app.loading') : t('app.tenant.products.form.image_ai.action') }}
                </button>

                <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isFetchingRepository || (ean ?? '').trim() === ''"
                    @click="fetchFromRepository"
                >
                    {{ isFetchingRepository ? t('app.loading') : t('app.tenant.products.form.image_repository.action') }}
                </button>
            </div>

            <input
                :id="`${name}-file`"
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="accept"
                @change="onFileChange"
            />

            <div class="flex min-h-40 items-center justify-center rounded-md border border-dashed border-border bg-background p-2">
                <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    :alt="label"
                    class="max-h-60 w-auto rounded object-contain"
                />
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('app.tenant.products.form.image_upload.empty') }}
                </p>
            </div>

            <p class="mt-2 truncate text-xs text-muted-foreground" :title="storedPath">
                {{ storedPath || t('app.tenant.products.form.image_upload.no_path') }}
            </p>
        </div>
    </div>
</template>
