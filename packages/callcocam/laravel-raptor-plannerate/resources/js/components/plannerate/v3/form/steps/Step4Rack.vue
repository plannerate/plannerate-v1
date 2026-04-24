<script lang="ts">
export const validate = (data: {
    rackWidth: number;
    holeHeight: number;
    holeWidth: number;
    holeSpacing: number;
}): boolean => {
    // Validações do backend: todos >= 1
    return (
        (data.rackWidth ?? 0) >= 1 &&
        (data.holeHeight ?? 0) >= 1 &&
        (data.holeWidth ?? 0) >= 1 &&
        (data.holeSpacing ?? 0) >= 1
    );
};
</script>

<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { GripVerticalIcon } from 'lucide-vue-next';

interface Props {
    modelValue: {
        rackWidth: number;
        holeHeight: number;
        holeWidth: number;
        holeSpacing: number;
    };
    errors?: Record<string, string>;
}

interface Emits {
    (e: 'update:modelValue', value: Props['modelValue']): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

function updateField<K extends keyof Props['modelValue']>(
    key: K,
    value: Props['modelValue'][K],
): void {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <div class="rounded-full bg-primary/10 p-2">
                <GripVerticalIcon class="h-5 w-5 text-primary" />
            </div>
            <h3 class="text-lg font-medium">Configurar Cremalheira</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="rackWidth">Largura da Cremalheira (cm) *</Label>
                <Input
                    id="rackWidth"
                    type="number"
                    :model-value="props.modelValue.rackWidth"
                    @update:model-value="(val) => updateField('rackWidth', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.rackWidth,
                    }"
                />
                <p v-if="errors?.rackWidth" class="text-xs text-red-500">
                    {{ errors.rackWidth }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Largura da coluna vertical (cremalheira)
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="holeHeight">Altura do Furo (cm) *</Label>
                <Input
                    id="holeHeight"
                    type="number"
                    :model-value="props.modelValue.holeHeight"
                    @update:model-value="(val) => updateField('holeHeight', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.holeHeight,
                    }"
                />
                <p v-if="errors?.holeHeight" class="text-xs text-red-500">
                    {{ errors.holeHeight }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="holeWidth">Largura do Furo (cm) *</Label>
                <Input
                    id="holeWidth"
                    type="number"
                    :model-value="props.modelValue.holeWidth"
                    @update:model-value="(val) => updateField('holeWidth', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.holeWidth,
                    }"
                />
                <p v-if="errors?.holeWidth" class="text-xs text-red-500">
                    {{ errors.holeWidth }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="holeSpacing">Espaçamento Vertical (cm) *</Label>
                <Input
                    id="holeSpacing"
                    type="number"
                    :model-value="props.modelValue.holeSpacing"
                    @update:model-value="(val) => updateField('holeSpacing', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.holeSpacing,
                    }"
                />
                <p v-if="errors?.holeSpacing" class="text-xs text-red-500">
                    {{ errors.holeSpacing }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Distância vertical entre furos
                </p>
            </div>
        </div>

        <div
            class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
        >
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">Dica:</span> A cremalheira é a
                estrutura vertical com furos onde as prateleiras são encaixadas.
                O espaçamento entre os furos determina as posições possíveis das
                prateleiras.
            </p>
        </div>
    </div>
</template>
