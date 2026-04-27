<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Upload } from 'lucide-vue-next';
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
import { Input } from '@/components/ui/input';

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
    }>(),
    {
        accept: '.xlsx,.xls',
    }
);

const isOpen = ref(false);
const form = useForm<{ spreadsheet: File | null }>({
    spreadsheet: null,
});

function handleChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.spreadsheet = target.files?.[0] ?? null;
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
                <Input type="file" name="spreadsheet" :accept="accept" @change="handleChange" />
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
