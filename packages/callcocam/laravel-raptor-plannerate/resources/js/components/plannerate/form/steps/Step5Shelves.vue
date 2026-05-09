<script lang="ts">
export const validate = (data: {
    shelfHeight: number;
    shelfWidth: number;
    shelfDepth: number;
    numShelves: number;
    productType: 'normal' | 'hook';
}): boolean => {
    // Validações do backend: altura/largura/profundidade >= 1, numShelves >= 0, productType obrigatório
    // Usa validação do composable
    return validateShelfFields({
        shelfHeight: data.shelfHeight,
        shelfWidth: data.shelfWidth,
        shelfDepth: data.shelfDepth,
        numShelves: data.numShelves,
        productType: data.productType,
    });
};
</script>

<script setup lang="ts">
import { RulerIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { calculateUsableHeight } from '@/composables/plannerate/useSectionFields';
import {
    calculateShelfSpacing,
    calculateTotalDisplayArea,
    validateShelfFields,
} from '@/composables/plannerate/useShelfFields';
import { useT } from '@/composables/useT';

interface Props {
    modelValue: {
        shelfHeight: number;
        shelfWidth: number;
        shelfDepth: number;
        numShelves: number;
        productType: 'normal' | 'hook';
    };
    moduleData: {
        height: number;
        baseHeight: number;
        numModules: number;
    };
    errors?: Record<string, string>;
}

interface Emits {
    (e: 'update:modelValue', value: Props['modelValue']): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useT();

const updateValue = (key: keyof Props['modelValue'], value: number | string) => {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
};

const setProductType = (type: 'normal' | 'hook') => {
    updateValue('productType', type);
};

// Computed values usando composables
const usableHeight = computed(() => {
    return calculateUsableHeight(
        props.moduleData.height,
        props.moduleData.baseHeight,
    );
});

const usableHeightDisplay = computed(() => {
    return usableHeight.value > 0 ? usableHeight.value : 0;
});

const calculateSpacing = () => {
    if (props.modelValue.numShelves === 0) {
return '0';
}

    const spacing = calculateShelfSpacing(
        usableHeight.value,
        props.modelValue.shelfHeight,
        props.modelValue.numShelves,
    );

    return spacing > 0 ? spacing.toFixed(1) : '0';
};

const calculateDisplayArea = () => {
    const totalArea = calculateTotalDisplayArea(
        props.modelValue.shelfWidth,
        props.modelValue.shelfDepth,
        props.modelValue.numShelves,
        props.moduleData.numModules,
    );

    return totalArea.toFixed(0);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <div class="rounded-full bg-primary/10 p-2">
                <RulerIcon class="h-5 w-5 text-primary" />
            </div>
            <h3 class="text-lg font-medium">{{ t('plannerate.form.step5.title') }}</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="space-y-2">
                <Label for="shelfHeight">{{ t('plannerate.form.step5.thickness') }} (cm) *</Label>
                <Input
                    id="shelfHeight"
                    type="number"
                    :model-value="modelValue.shelfHeight"
                    @update:model-value="updateValue('shelfHeight', $event)"
                    min="1"
                    :class="{
                        'border-red-500': errors?.shelfHeight,
                    }"
                />
                <p v-if="errors?.shelfHeight" class="text-xs text-red-500">
                    {{ errors.shelfHeight }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="shelfDepth">{{ t('plannerate.print.product_detail.depth') }} (cm) *</Label>
                <Input
                    id="shelfDepth"
                    type="number"
                    :model-value="modelValue.shelfDepth"
                    @update:model-value="updateValue('shelfDepth', $event)"
                    min="1"
                    :class="{
                        'border-red-500': errors?.shelfDepth,
                    }"
                />
                <p v-if="errors?.shelfDepth" class="text-xs text-red-500">
                    {{ errors.shelfDepth }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="numShelves">{{ t('plannerate.form.step5.number_of_shelves') }} *</Label>
                <Input
                    id="numShelves"
                    type="number"
                    :model-value="modelValue.numShelves"
                    @update:model-value="updateValue('numShelves', $event)"
                    min="0"
                    :class="{
                        'border-red-500': errors?.numShelves,
                    }"
                />
                <p v-if="errors?.numShelves" class="text-xs text-red-500">
                    {{ errors.numShelves }}
                </p>
            </div>
        </div>

        <div
            class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
        >
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">{{ t('plannerate.form.info') }}:</span>
                {{ t('plannerate.form.step5.width_auto_prefix') }} ({{ moduleData.width }} cm).
            </p>
        </div>

        <div class="space-y-2">
            <Label>{{ t('plannerate.form.step5.default_product_type') }} *</Label>
            <div
                class="grid grid-cols-2 gap-2 rounded-md border"
                :class="{
                    'border-red-500': errors?.productType,
                }"
            >
                <Button
                    :variant="
                        props.modelValue.productType === 'normal'
                            ? 'default'
                            : 'outline'
                    "
                    @click="setProductType('normal')"
                    type="button"
                    class="justify-center rounded-r-none border-r"
                >
                    {{ t('plannerate.sidebar.shelf_details.normal') }}
                </Button>
                <Button
                    :variant="
                        props.modelValue.productType === 'hook' ? 'default' : 'outline'
                    "
                    @click="setProductType('hook')"
                    type="button"
                    class="justify-center rounded-l-none"
                >
                    {{ t('plannerate.sidebar.shelf_details.hook') }}
                </Button>
            </div>
            <p v-if="errors?.productType" class="text-xs text-red-500">
                {{ errors.productType }}
            </p>
        </div>

        <div class="space-y-2 rounded-lg border p-4">
            <h4 class="text-sm font-medium">{{ t('plannerate.form.step5.calculations') }}</h4>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <span>{{ t('plannerate.form.step5.usable_height') }}:</span>
                    <span>{{ usableHeightDisplay }} cm</span>
                </div>
                <div class="flex justify-between">
                    <span>{{ t('plannerate.form.step5.average_spacing') }}:</span>
                    <span>{{ calculateSpacing() }} cm</span>
                </div>
                <div class="flex justify-between">
                    <span>{{ t('plannerate.form.step5.total_display_area') }}:</span>
                    <span>{{ calculateDisplayArea() }} cm²</span>
                </div>
            </div>
        </div>

        <div
            class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
        >
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">{{ t('plannerate.form.tip') }}:</span>
                {{ t('plannerate.form.step5.tip_text') }}
            </p>
        </div>
    </div>
</template>
