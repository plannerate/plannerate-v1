<template>
    <div class="space-y-2">
        <Label class="text-xs font-semibold">Dimensões do Produto</Label>
        <div class="grid grid-cols-3 gap-2 text-sm">
            <div>
                <span class="text-muted-foreground">A:</span>
                <Input
                    type="number"
                    step="0.01"
                    :model-value="height"
                    @update:model-value="
                        $emit('update:height', Number($event))
                    "
                />
            </div>
            <div>
                <span class="text-muted-foreground">L:</span>
                <Input
                    type="number"
                    step="0.01"
                    :model-value="width"
                    @update:model-value="
                        $emit('update:width', Number($event))
                    "
                />
            </div>
            <div>
                <span class="text-muted-foreground">P:</span>
                <Input
                    type="number"
                    step="0.01"
                    :model-value="depth"
                    @update:model-value="
                        $emit('update:depth', Number($event))
                    "
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    height?: number;
    width?: number;
    depth?: number;
}

defineProps<Props>();

defineEmits<{
    'update:height': [value: number];
    'update:width': [value: number];
    'update:depth': [value: number];
}>();
</script>
