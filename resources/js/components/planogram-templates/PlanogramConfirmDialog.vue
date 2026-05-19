<script setup lang="ts">
import { AlertTriangle, ArrowRightLeft, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        confirmLabel: string;
        cancelLabel?: string;
        kind?: 'delete' | 'move' | 'default';
        busy?: boolean;
    }>(),
    {
        cancelLabel: 'Cancelar',
        kind: 'default',
        busy: false,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();

const isDestructive = computed(() => props.kind === 'delete');
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full"
                        :class="
                            isDestructive
                                ? 'bg-destructive/10'
                                : 'bg-primary/10'
                        "
                    >
                        <Trash2
                            v-if="kind === 'delete'"
                            class="size-5 text-destructive"
                        />
                        <ArrowRightLeft
                            v-else-if="kind === 'move'"
                            class="size-5 text-primary"
                        />
                        <AlertTriangle v-else class="size-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle>{{ title }}</DialogTitle>
                        <DialogDescription class="mt-1">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="busy"
                    @click="emit('update:open', false)"
                >
                    {{ cancelLabel }}
                </Button>
                <Button
                    :variant="isDestructive ? 'destructive' : 'default'"
                    :disabled="busy"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
