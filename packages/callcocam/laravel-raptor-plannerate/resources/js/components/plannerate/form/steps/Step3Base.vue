<script lang="ts">
export const validate = (data: {
    baseHeight: number;
    baseWidth: number;
    baseDepth: number;
}): boolean => {
    // Validações do backend: todos >= 1
    return (
        (data.baseHeight ?? 0) >= 1 &&
        (data.baseWidth ?? 0) >= 1 &&
        (data.baseDepth ?? 0) >= 1
    );
};
</script>

<script setup lang="ts">
import { BoxIcon } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

interface Props {
    modelValue: {
        baseHeight: number;
        baseWidth: number;
        baseDepth: number;
    };
    errors?: Record<string, string>;
}

interface Emits {
    (e: 'update:modelValue', value: Props['modelValue']): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useT();

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
                <BoxIcon class="h-5 w-5 text-primary" />
            </div>
            <h3 class="text-lg font-medium">{{ t('plannerate.form.step3.title') }}</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="baseHeight">{{ t('plannerate.form.step3.base_height') }} (cm) *</Label>
                <Input
                    id="baseHeight"
                    type="number"
                    :model-value="props.modelValue.baseHeight"
                    @update:model-value="(val) => updateField('baseHeight', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.baseHeight,
                    }"
                />
                <p v-if="errors?.baseHeight" class="text-xs text-red-500">
                    {{ errors.baseHeight }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="baseDepth">{{ t('plannerate.form.step3.base_depth') }} (cm) *</Label>
                <Input
                    id="baseDepth"
                    type="number"
                    :model-value="props.modelValue.baseDepth"
                    @update:model-value="(val) => updateField('baseDepth', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.baseDepth,
                    }"
                />
                <p v-if="errors?.baseDepth" class="text-xs text-red-500">
                    {{ errors.baseDepth }}
                </p>
            </div>
        </div>

        <div
            class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
        >
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">{{ t('plannerate.form.tip') }}:</span>
                {{ t('plannerate.form.step3.tip_prefix') }}
                {{ modelValue.baseWidth }} cm).
            </p>
        </div>
    </div>
</template>
