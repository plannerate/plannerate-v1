<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileSpreadsheet, TriangleAlert, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        action: string;
        buttonLabel: string;
        title: string;
        description: string;
        fileLabel: string;
        submitLabel: string;
        submittingLabel: string;
        cancelLabel: string;
        accept?: string;
        dropLabel?: string;
        dropHint?: string;
        showTruncateOption?: boolean;
        truncateLabel?: string;
        truncateWarning?: string;
    }>(),
    {
        accept: '.xlsx,.xls',
        dropLabel: 'Arraste e solte a planilha aqui',
        dropHint: 'ou clique para escolher um arquivo',
        showTruncateOption: false,
        truncateLabel: 'Excluir tudo antes de importar',
        truncateWarning: 'Atenção: todos os registros existentes serão excluídos permanentemente. Esta ação não poderá ser desfeita.',
    }
);

const isOpen = ref(false);
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm<{ spreadsheet: File | null; truncate_before_import: boolean }>({
    spreadsheet: null,
    truncate_before_import: false,
});

function setSpreadsheet(file: File | null): void {
    form.spreadsheet = file;
}

function handleChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    setSpreadsheet(target.files?.[0] ?? null);
}

function openFilePicker(): void {
    fileInput.value?.click();
}

function onDragOver(event: DragEvent): void {
    event.preventDefault();

    if (form.processing) {
        return;
    }

    isDragging.value = true;
}

function onDragLeave(): void {
    isDragging.value = false;
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = false;

    if (form.processing) {
        return;
    }

    const file = event.dataTransfer?.files?.[0] ?? null;
    setSpreadsheet(file);
}

function submit(): void {
    form
        .transform((data) => {
            if (!props.showTruncateOption) {
                const { truncate_before_import: _, ...rest } = data;
                return rest;
            }
            return data;
        })
        .post(props.action, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                isOpen.value = false;
            },
        });
}

function close(): void {
    if (form.processing) {
        return;
    }

    form.clearErrors();
    form.reset();
    isDragging.value = false;
    isOpen.value = false;
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="outline" size="pill-sm" class="inline-flex items-center gap-2">
                <Upload class="size-4" />
                {{ buttonLabel }}
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <label class="text-sm font-medium text-foreground">{{ fileLabel }}</label>
                <input
                    ref="fileInput"
                    type="file"
                    name="spreadsheet"
                    class="hidden"
                    :accept="accept"
                    @change="handleChange"
                />
                <button
                    type="button"
                    class="w-full rounded-lg border-2 border-dashed border-muted-foreground/30 bg-muted/20 px-4 py-5 text-left transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="{
                        'border-primary bg-primary/5': isDragging,
                        'opacity-70 cursor-not-allowed': form.processing,
                        'hover:border-primary/60 hover:bg-primary/5': !form.processing,
                    }"
                    :disabled="form.processing"
                    @click="openFilePicker"
                    @dragover="onDragOver"
                    @dragleave="onDragLeave"
                    @drop="onDrop"
                >
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-background p-2">
                            <FileSpreadsheet class="size-5 text-primary" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-foreground">
                                {{ form.spreadsheet?.name || dropLabel }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ dropHint }}
                            </p>
                        </div>
                    </div>
                </button>
                <p v-if="form.errors.spreadsheet" class="text-sm text-destructive">
                    {{ form.errors.spreadsheet }}
                </p>
            </div>

            <div v-if="showTruncateOption" class="space-y-3">
                <label
                    class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 transition-colors"
                    :class="
                        form.truncate_before_import
                            ? 'border-destructive/60 bg-destructive/10 dark:bg-destructive/20'
                            : 'border-border hover:border-destructive/40 hover:bg-destructive/5'
                    "
                >
                    <Checkbox
                        :checked="form.truncate_before_import"
                        :disabled="form.processing"
                        @update:model-value="(v) => (form.truncate_before_import = v === true)"
                    />
                    <span
                        class="text-sm font-semibold transition-colors"
                        :class="form.truncate_before_import ? 'text-destructive' : 'text-foreground'"
                    >
                        {{ truncateLabel }}
                    </span>
                </label>

                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="form.truncate_before_import"
                        class="rounded-md border-2 border-destructive bg-destructive/10 p-3 dark:bg-destructive/20"
                    >
                        <div class="mb-1.5 flex items-center gap-2">
                            <TriangleAlert class="size-4 shrink-0 text-destructive" />
                            <span class="text-sm font-bold uppercase tracking-wide text-destructive">
                                Ação irreversível
                            </span>
                        </div>
                        <p class="text-sm text-destructive">{{ truncateWarning }}</p>
                    </div>
                </Transition>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="form.processing" @click="close">
                    {{ cancelLabel }}
                </Button>
                <Button
                    :variant="form.truncate_before_import ? 'destructive' : 'default'"
                    :disabled="!form.spreadsheet || form.processing"
                    @click="submit"
                >
                    {{ form.processing ? submittingLabel : submitLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
