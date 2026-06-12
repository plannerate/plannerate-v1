<script setup lang="ts">
import { Copy } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type CopyPromptAction = {
    key: string;
    label: string;
    variant?: 'default' | 'outline' | 'ghost';
};

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        actions: CopyPromptAction[];
        busy?: boolean;
    }>(),
    {
        busy: false,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    action: [key: string];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10"
                    >
                        <Copy class="size-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle>{{ title }}</DialogTitle>
                        <DialogDescription class="mt-1">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogFooter class="flex-col gap-2 sm:flex-col sm:space-x-0">
                <Button
                    v-for="action in actions"
                    :key="action.key"
                    :variant="action.variant ?? 'default'"
                    :disabled="busy"
                    class="w-full"
                    @click="emit('action', action.key)"
                >
                    {{ action.label }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
