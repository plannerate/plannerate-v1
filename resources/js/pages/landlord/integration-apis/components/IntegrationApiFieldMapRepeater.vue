<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import IntegrationApiTransformTags from './IntegrationApiTransformTags.vue';
import type { FieldMapRow } from './types';

const props = defineProps<{
    modelValue: FieldMapRow[];
    targetOptions: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: FieldMapRow[]];
}>();

const { t } = useT();

function newRow(): FieldMapRow {
    return {
        id: `field-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        target: '',
        source: '',
        transforms: [],
    };
}

function addRow(): void {
    emit('update:modelValue', [...props.modelValue, newRow()]);
}

function updateRow(index: number, patch: Partial<FieldMapRow>): void {
    emit(
        'update:modelValue',
        props.modelValue.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)),
    );
}

function removeRow(index: number): void {
    emit('update:modelValue', props.modelValue.filter((_, rowIndex) => rowIndex !== index));
}

function internalFieldOptions(value: string): string[] {
    if (value.trim() !== '' && ! props.targetOptions.includes(value)) {
        return [value, ...props.targetOptions];
    }

    return props.targetOptions;
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-3">
            <p class="text-sm font-medium text-muted-foreground">{{ t('app.landlord.integration_apis.fields.field_map') }}</p>
        </div>

        <div class="space-y-2">
            <div
                v-for="(row, index) in props.modelValue"
                :key="row.id"
                class="grid grid-cols-1 gap-3 rounded-md border border-border bg-background p-3 md:grid-cols-12"
            >
                <div class="grid gap-2 md:col-span-3">
                    <Label :for="`field-target-${row.id}`">{{ t('app.landlord.integration_apis.fields.internal_field') }}</Label>
                    <select
                        v-if="props.targetOptions.length > 0"
                        :id="`field-target-${row.id}`"
                        :value="row.target"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                        @change="updateRow(index, { target: ($event.target as HTMLSelectElement).value })"
                    >
                        <option value="">{{ t('app.landlord.integration_apis.placeholders.internal_field') }}</option>
                        <option v-for="option in internalFieldOptions(row.target)" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                    <Input
                        v-else
                        :id="`field-target-${row.id}`"
                        :model-value="row.target"
                        :placeholder="t('app.landlord.integration_apis.placeholders.internal_field')"
                        @update:model-value="updateRow(index, { target: String($event) })"
                    />
                </div>
                <div class="grid gap-2 md:col-span-3">
                    <Label :for="`field-source-${row.id}`">{{ t('app.landlord.integration_apis.fields.api_field') }}</Label>
                    <Input
                        :id="`field-source-${row.id}`"
                        :model-value="row.source"
                        :placeholder="t('app.landlord.integration_apis.placeholders.api_field')"
                        @update:model-value="updateRow(index, { source: String($event) })"
                    />
                </div>
                <div class="grid gap-2 md:col-span-5">
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

            <div
                v-if="props.modelValue.length === 0"
                class="rounded-md border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground"
            >
                {{ t('app.landlord.integration_apis.empty_states.field_map') }}
            </div>
        </div>

        <div class="flex justify-center pt-1">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                @click="addRow"
            >
                <Plus class="size-3.5" />
                {{ t('app.landlord.integration_apis.actions.add_field') }}
            </button>
        </div>
    </div>
</template>
