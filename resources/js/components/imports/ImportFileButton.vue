<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileSpreadsheet, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
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
    }>(),
    {
        accept: '.xlsx,.xls',
        dropLabel: 'Arraste e solte a planilha aqui',
        dropHint: 'ou clique para escolher um arquivo',
    }
);

const isOpen = ref(false);
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm<{ spreadsheet: File | null }>({
    spreadsheet: null,
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
    form.post(props.action, {
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

            <DialogFooter>
                <Button variant="outline" :disabled="form.processing" @click="close">
                    {{ cancelLabel }}
                </Button>
                <Button :disabled="!form.spreadsheet || form.processing" @click="submit">
                    {{ form.processing ? submittingLabel : submitLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
