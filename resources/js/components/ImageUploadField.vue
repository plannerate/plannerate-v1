<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
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
const imageUrlInput = ref('');
const urlPopoverOpen = ref(false);
const isUploading = ref(false);
const isProcessingAi = ref(false);
const isFetchingRepository = ref(false);
const isImportingFromUrl = ref(false);

const uploadHttp = useHttp<{ file: File | null }, { path?: string; public_url?: string }>({
    file: null,
});
const aiProcessHttp = useHttp<{ path: string }, { id?: string; status?: string }>({
    path: '',
});
const statusHttp = useHttp<Record<string, never>, {
    status?: string;
    path?: string;
    public_url?: string;
    error_message?: string;
}>({});
const repositoryHttp = useHttp<{ ean: string; process_with_ai: boolean }, { path?: string; public_url?: string; ai_processed?: boolean; ai_error?: string }>({
    ean: '',
    process_with_ai: false,
});

function toHttpRoute(route: { url: string; method: string }): UrlMethodPair {
    return {
        url: tenantWayfinderPath(route.url),
        method: route.method as UrlMethodPair['method'],
    };
}

function resolveHttpErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object') {
        const responseMessage = (error as {
            response?: { data?: { message?: string } };
        }).response?.data?.message;

        if (typeof responseMessage === 'string' && responseMessage.trim() !== '') {
            return responseMessage;
        }
    }

    if (error instanceof Error && error.message.trim() !== '') {
        return error.message;
    }

    return fallback;
}

function debugHttpError(error: unknown, context: string): void {
    if (typeof console !== 'undefined') {
        console.group(`[ImageUploadField] ${context}`);
        console.error(error);

        if (error && typeof error === 'object') {
            const typedError = error as {
                message?: string;
                response?: {
                    status?: number;
                    statusText?: string;
                    data?: unknown;
                    config?: { method?: string; url?: string };
                };
            };
            console.info('message', typedError.message ?? null);
            console.info('status', typedError.response?.status ?? null);
            console.info('statusText', typedError.response?.statusText ?? null);
            console.info('method', typedError.response?.config?.method ?? null);
            console.info('url', typedError.response?.config?.url ?? null);
            console.info('response.data', typedError.response?.data ?? null);
        }

        console.groupEnd();
    }
}

function resolveAiFriendlyError(message: string): string {
    const normalized = message.toLowerCase();

    if (normalized.includes('openai_api_key')) {
        return t('app.tenant.products.form.image_ai.missing_api_key');
    }

    if (
        normalized.includes('insufficient_quota')
        || normalized.includes('quota')
        || normalized.includes('billing')
        || normalized.includes('429')
        || normalized.includes('rate limit')
    ) {
        return t('app.tenant.products.form.image_ai.no_credits');
    }

    return message;
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
        const message = t('app.tenant.products.form.image_upload.too_large', { size: String(props.maxSizeMb) });
        emit('error', message);
        target.value = '';

        return;
    }

    selectedFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
    void uploadSelectedFile();
}

async function importFromUrl(): Promise<void> {
    const url = imageUrlInput.value.trim();

    if (url === '' || isImportingFromUrl.value || isUploading.value) {
        return;
    }

    try {
         
        new URL(url);
    } catch {
        emit('error', t('app.tenant.products.form.image_upload.invalid_url'));

        return;
    }

    isImportingFromUrl.value = true;

    try {
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(t('app.tenant.products.form.image_upload.import_failed'));
        }

        const blob = await response.blob();

        if (!blob.type.startsWith('image/')) {
            throw new Error(t('app.tenant.products.form.image_upload.invalid_url_image'));
        }

        if (blob.size > props.maxSizeMb * 1024 * 1024) {
            throw new Error(t('app.tenant.products.form.image_upload.too_large', { size: String(props.maxSizeMb) }));
        }

        const fileExtension = blob.type.split('/')[1] ?? 'jpg';
        const fileName = `imported-image.${fileExtension}`;
        const importedFile = new File([blob], fileName, { type: blob.type });

        selectedFile.value = importedFile;
        previewUrl.value = URL.createObjectURL(importedFile);
        await uploadSelectedFile();
        urlPopoverOpen.value = false;
    } catch (error) {
        const message = error instanceof Error
            ? error.message
            : t('app.tenant.products.form.image_upload.import_failed');
        emit('error', message);
    } finally {
        isImportingFromUrl.value = false;
    }
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

async function processWithAi(): Promise<boolean> {
    if (!props.aiEnabled || storedPath.value === '' || isProcessingAi.value) {
        return false;
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

        return true;
    } catch (error) {
        const message = error instanceof Error
            ? error.message
            : t('app.tenant.products.form.image_ai.start_failed');
        emit('error', message);
        isProcessingAi.value = false;

        return false;
    }
}

async function fetchFromRepository(): Promise<boolean> {
    if (isFetchingRepository.value) {
        return false;
    }

    const currentEan = (props.ean ?? '').trim();

    if (currentEan === '') {
        emit('error', t('app.tenant.products.form.image_repository.ean_required'));

        return false;
    }

    isFetchingRepository.value = true;

    try {
        repositoryHttp.ean = currentEan;
        repositoryHttp.process_with_ai = props.aiEnabled;

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

        if (payload.ai_processed === true) {
            emit('aiProcessed', payload.path);
        } else if (typeof payload.ai_error === 'string' && payload.ai_error !== '') {
            emit('error', resolveAiFriendlyError(payload.ai_error));
        }

        emit('repositoryProcessed', payload.path);

        return true;
    } catch (error) {
        debugHttpError(error, 'repository.fetch');

        const repositoryRoute = imageRoutes.repository.fetch(props.subdomain);
        const typedError = error as {
            response?: {
                status?: number;
                config?: { method?: string; url?: string };
                data?: { message?: string; errors?: unknown; debug?: unknown };
            };
        };
        const details = {
            status: typedError.response?.status ?? null,
            method: typedError.response?.config?.method ?? 'post',
            url: typedError.response?.config?.url ?? tenantWayfinderPath(repositoryRoute.url),
            expectedAbsoluteUrl: repositoryRoute.url,
            currentHost: typeof window !== 'undefined' ? window.location.host : null,
            currentPath: typeof window !== 'undefined' ? window.location.pathname : null,
            subdomain: props.subdomain,
            message: typedError.response?.data?.message ?? null,
            errors: typedError.response?.data?.errors ?? null,
            backendDebug: typedError.response?.data?.debug ?? null,
        };

        if (typeof console !== 'undefined') {
            console.info('[ImageUploadField] repository.fetch debug details', details);
        }

        const primaryMessage = details.status === 404 && !details.message
            ? t('app.tenant.products.form.image_repository.not_found')
            : resolveHttpErrorMessage(
                error,
                t('app.tenant.products.form.image_repository.fetch_failed')
            );
        emit('error', primaryMessage);

        return false;
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
            <div class="mb-3 grid grid-cols-1 md:grid-cols-2 items-center gap-3">
                <button type="button"
                    class="inline-flex items-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
                    :disabled="isUploading" @click="openPicker">
                    {{ isUploading ? t('app.loading') : t('app.tenant.products.form.image_upload.select') }}
                </button>

                <button v-if="aiEnabled" type="button"
                    class="inline-flex items-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isProcessingAi || storedPath === ''" @click="processWithAi">
                    {{ isProcessingAi ? t('app.loading') : t('app.tenant.products.form.image_ai.action') }}
                </button>

                <button type="button"
                    class="inline-flex items-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isFetchingRepository || (ean ?? '').trim() === ''" @click="fetchFromRepository">
                    {{ isFetchingRepository ? t('app.loading') : t('app.tenant.products.form.image_repository.action')
                    }}
                </button>
                <Popover v-model:open="urlPopoverOpen">
                    <PopoverTrigger as-child>
                        <button type="button"
                            class="inline-flex items-center justify-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted">
                            {{ t('app.tenant.products.form.image_upload.import_url') }}
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[34rem] max-w-[95vw] space-y-3 p-4" align="start">
                        <p class="text-sm font-medium">
                            {{ t('app.tenant.products.form.image_upload.url_placeholder') }}
                        </p>

                        <div class="flex flex-col gap-2">
                            <input v-model="imageUrlInput" type="url"
                                class="h-10 w-full rounded-md border border-border bg-background px-3 text-sm"
                                :placeholder="t('app.tenant.products.form.image_upload.url_placeholder')"
                                @keydown.enter.prevent="importFromUrl" />
                            <button type="button"
                                class="inline-flex items-center justify-center rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="isImportingFromUrl || isUploading || imageUrlInput.trim() === ''"
                                @click="importFromUrl">
                                {{ isImportingFromUrl ? t('app.loading') :
                                    t('app.tenant.products.form.image_upload.import_url') }}
                            </button>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>

            <input :id="`${name}-file`" ref="fileInput" type="file" class="hidden" :accept="accept"
                @change="onFileChange" />



            <div
                class="flex min-h-40 items-center justify-center rounded-md border border-dashed border-border bg-background p-2">
                <img v-if="previewUrl" :src="previewUrl" :alt="label" class="max-h-60 w-auto rounded object-contain" />
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
