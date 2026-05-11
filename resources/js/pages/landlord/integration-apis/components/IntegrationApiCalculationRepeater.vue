<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import IntegrationApiTransformTags from './IntegrationApiTransformTags.vue';
import type { CalculationRow } from './types';

const props = defineProps<{
    modelValue: CalculationRow[];
    fieldOptions: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: CalculationRow[]];
}>();

const { t } = useT();
const uniqueFieldOptions = computed(() => Array.from(new Set(props.fieldOptions.filter((field) => field.trim() !== ''))));
const operations = ['sum', 'subtract', 'multiply', 'divide', 'coalesce', 'concat'];

function newRow(): CalculationRow {
    return {
        id: `calc-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        target: '',
        operation: 'multiply',
        operands: [],
        transforms: [],
    };
}

function addRow(): void {
    emit('update:modelValue', [...props.modelValue, newRow()]);
}

function updateRow(index: number, patch: Partial<CalculationRow>): void {
    emit(
        'update:modelValue',
        props.modelValue.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)),
    );
}

function removeRow(index: number): void {
    emit('update:modelValue', props.modelValue.filter((_, rowIndex) => rowIndex !== index));
}

function toggleOperand(index: number, operand: string): void {
    const row = props.modelValue[index];
    const selected = new Set(row.operands);

    if (selected.has(operand)) {
        selected.delete(operand);
    } else {
        selected.add(operand);
    }

    updateRow(index, { operands: Array.from(selected) });
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-3">
            <p class="text-sm font-medium text-muted-foreground">{{ t('app.landlord.integration_apis.fields.calculations') }}</p>
        </div>

        <div class="space-y-2">
            <div
                v-for="(row, index) in props.modelValue"
                :key="row.id"
                class="space-y-3 rounded-md border border-border bg-background p-3"
            >
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`calc-target-${row.id}`">{{ t('app.landlord.integration_apis.fields.calculated_field') }}</Label>
                        <Input
                            :id="`calc-target-${row.id}`"
                            :model-value="row.target"
                            :placeholder="t('app.landlord.integration_apis.placeholders.calculated_field')"
                            @update:model-value="updateRow(index, { target: String($event) })"
                        />
                    </div>
                    <div class="grid gap-2 md:col-span-3">
                        <Label :for="`calc-operation-${row.id}`">{{ t('app.landlord.integration_apis.fields.operation') }}</Label>
                        <select
                            :id="`calc-operation-${row.id}`"
                            :value="row.operation"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            @change="updateRow(index, { operation: ($event.target as HTMLSelectElement).value })"
                        >
                            <option v-for="operation in operations" :key="operation" :value="operation">
                                {{ t(`app.landlord.integration_apis.operations.${operation}`) }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2 md:col-span-4">
                        <Label>{{ t('app.landlord.integration_apis.fields.transforms') }}</Label>
                        <IntegrationApiTransformTags
                            :model-value="row.transforms"
                            @update:model-value="updateRow(index, { transforms: $event })"
                        />
                    </div>
                    <div class="flex items-end justify-end md:col-span-1">
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                            @click="removeRow(index)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>{{ t('app.landlord.integration_apis.fields.operands') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="field in uniqueFieldOptions"
                            :key="field"
                            type="button"
                            class="h-7 rounded-md border px-2 text-xs transition"
                            :class="
                                row.operands.includes(field)
                                    ? 'border-primary/60 bg-primary/10 text-primary'
                                    : 'border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground'
                            "
                            @click="toggleOperand(index, field)"
                        >
                            {{ field }}
                        </button>
                        <span v-if="uniqueFieldOptions.length === 0" class="text-sm text-muted-foreground">
                            {{ t('app.landlord.integration_apis.empty_states.operands') }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="props.modelValue.length === 0"
                class="rounded-md border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground"
            >
                {{ t('app.landlord.integration_apis.empty_states.calculations') }}
            </div>
        </div>

        <div class="flex justify-center pt-1">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                @click="addRow"
            >
                <Plus class="size-3.5" />
                {{ t('app.landlord.integration_apis.actions.add_calculation') }}
            </button>
        </div>
    </div>
</template>
