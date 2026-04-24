<script lang="ts">
export const validate = (data: {
    height: number;
    width: number;
    numModules: number;
}): boolean => {
    // Validações do backend: height >= 1, width >= 1, numModules >= 1
    return (
        (data.height ?? 0) >= 1 &&
        (data.width ?? 0) >= 1 &&
        (data.numModules ?? 0) >= 1
    );
};
</script>

<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LayoutGridIcon } from 'lucide-vue-next';
import {  } from 'vue';

interface Props {
    modelValue: {
        height: number;
        width: number;
        numModules: number;
    };
    errors?: Record<string, string>;
}

interface Emits {
    (e: 'update:modelValue', value: Props['modelValue']): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const updateValue = (key: keyof Props['modelValue'], value: number) => {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <div class="rounded-full bg-primary/10 p-2">
                <LayoutGridIcon class="h-5 w-5 text-primary" />
            </div>
            <h3 class="text-lg font-medium">Configurar Módulos</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="height">Altura do Módulo (cm) *</Label>
                <Input
                    id="height"
                    type="number"
                    :model-value="modelValue.height"
                    @update:model-value="updateValue('height', $event as number)"
                    min="1"
                    :class="{
                        'border-red-500': errors?.height,
                    }"
                />
                <p v-if="errors?.height" class="text-xs text-red-500">
                    {{ errors.height }}
                </p>
            </div>
            <div class="space-y-2">
                <Label for="width">Largura do Módulo (cm) *</Label>
                <Input
                    id="width"
                    type="number"
                    :model-value="modelValue.width"
                    @update:model-value="updateValue('width', $event as number)"
                    min="1"
                    :class="{
                        'border-red-500': errors?.width,
                    }"
                />
                <p v-if="errors?.width" class="text-xs text-red-500">
                    {{ errors.width }}
                </p>
            </div>
            <div class="space-y-2">
                <Label for="numModules">Número de Módulos *</Label>
                <Input
                    id="numModules"
                    type="number"
                    :model-value="modelValue.numModules"
                    @update:model-value="updateValue('numModules', $event as number)"
                    min="1"
                    :class="{
                        'border-red-500': errors?.numModules,
                    }"
                />
                <p v-if="errors?.numModules" class="text-xs text-red-500">
                    {{ errors.numModules }}
                </p>
            </div>
        </div>

        <div
            class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
        >
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">Dica:</span> A configuração de módulos
                define quantas divisões verticais a gôndola terá. Cada módulo
                pode ter suas próprias prateleiras.
            </p>
        </div>
    </div>
</template>
