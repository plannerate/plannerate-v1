<script setup lang="ts">
import { LayoutTemplate, Sparkles } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/composables/useT';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'choose': [mode: 'template' | 'automatic'];
}>();

const { t } = useT();

function choose(mode: 'template' | 'automatic'): void {
    emit('choose', mode);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.header.chooser.title') }}</DialogTitle>
                <DialogDescription>{{ t('plannerate.header.chooser.description') }}</DialogDescription>
            </DialogHeader>

            <div class="mt-2 grid grid-cols-2 gap-3">
                <button
                    type="button"
                    class="flex flex-col items-start gap-3 rounded-lg border-2 p-4 text-left transition-colors hover:border-primary hover:bg-primary/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @click="choose('template')"
                >
                    <LayoutTemplate class="size-6 text-amber-600" />
                    <div>
                        <p class="text-sm font-semibold">{{ t('plannerate.header.chooser.template_label') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('plannerate.header.chooser.template_description') }}</p>
                    </div>
                </button>

                <button
                    type="button"
                    class="flex flex-col items-start gap-3 rounded-lg border-2 p-4 text-left transition-colors hover:border-primary hover:bg-primary/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @click="choose('automatic')"
                >
                    <Sparkles class="size-6 text-primary" />
                    <div>
                        <p class="text-sm font-semibold">{{ t('plannerate.header.chooser.automatic_label') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('plannerate.header.chooser.automatic_description') }}</p>
                    </div>
                </button>
            </div>

            <div class="mt-4 flex justify-end">
                <Button variant="outline" @click="emit('update:open', false)">
                    {{ t('plannerate.common.cancel') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
