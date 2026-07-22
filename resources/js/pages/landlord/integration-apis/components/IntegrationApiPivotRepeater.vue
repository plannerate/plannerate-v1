<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import type { PivotTableRow } from './types';

const props = defineProps<{
    modelValue: PivotTableRow[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: PivotTableRow[]];
}>();

const { t } = useT();

function newRow(): PivotTableRow {
    return {
        id: `pivot-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        table: '',
        local_key: 'id',
        foreign_key: '',
        related_key: '',
        unique_by: '',
        update_columns: '',
    };
}

function addRow(): void {
    emit('update:modelValue', [...props.modelValue, newRow()]);
}

function updateRow(index: number, patch: Partial<PivotTableRow>): void {
    emit(
        'update:modelValue',
        props.modelValue.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)),
    );
}

function removeRow(index: number): void {
    emit('update:modelValue', props.modelValue.filter((_, rowIndex) => rowIndex !== index));
}
</script>

<template>
    <div class="space-y-3">
        <div class="space-y-2">
            <div
                v-for="(row, index) in props.modelValue"
                :key="row.id"
                class="grid grid-cols-1 gap-3 rounded-md border border-border bg-background p-3 md:grid-cols-12"
            >
                <div class="grid gap-2 md:col-span-3">
                    <Label :for="`pivot-table-${row.id}`">{{ t('app.landlord.integration_apis.fields.pivot_table') }}</Label>
                    <Input
                        :id="`pivot-table-${row.id}`"
                        :model-value="row.table"
                        :placeholder="t('app.landlord.integration_apis.placeholders.pivot_table')"
                        @update:model-value="updateRow(index, { table: String($event) })"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label :for="`pivot-local-key-${row.id}`">{{ t('app.landlord.integration_apis.fields.pivot_local_key') }}</Label>
                    <Input
                        :id="`pivot-local-key-${row.id}`"
                        :model-value="row.local_key"
                        :placeholder="t('app.landlord.integration_apis.placeholders.pivot_local_key')"
                        @update:model-value="updateRow(index, { local_key: String($event) })"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label :for="`pivot-foreign-key-${row.id}`">{{ t('app.landlord.integration_apis.fields.pivot_foreign_key') }}</Label>
                    <Input
                        :id="`pivot-foreign-key-${row.id}`"
                        :model-value="row.foreign_key"
                        :placeholder="t('app.landlord.integration_apis.placeholders.pivot_foreign_key')"
                        @update:model-value="updateRow(index, { foreign_key: String($event) })"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label :for="`pivot-related-key-${row.id}`">{{ t('app.landlord.integration_apis.fields.pivot_related_key') }}</Label>
                    <Input
                        :id="`pivot-related-key-${row.id}`"
                        :model-value="row.related_key"
                        :placeholder="t('app.landlord.integration_apis.placeholders.pivot_related_key')"
                        @update:model-value="updateRow(index, { related_key: String($event) })"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label :for="`pivot-unique-by-${row.id}`">{{ t('app.landlord.integration_apis.fields.pivot_unique_by') }}</Label>
                    <Input
                        :id="`pivot-unique-by-${row.id}`"
                        :model-value="row.unique_by"
                        :placeholder="t('app.landlord.integration_apis.placeholders.pivot_unique_by')"
                        @update:model-value="updateRow(index, { unique_by: String($event) })"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label :for="`pivot-update-columns-${row.id}`">{{ t('app.landlord.integration_apis.fields.pivot_update_columns') }}</Label>
                    <Input
                        :id="`pivot-update-columns-${row.id}`"
                        :model-value="row.update_columns"
                        :placeholder="t('app.landlord.integration_apis.placeholders.pivot_update_columns')"
                        @update:model-value="updateRow(index, { update_columns: String($event) })"
                    />
                    <p class="text-xs text-muted-foreground">{{ t('app.landlord.integration_apis.hints.pivot_update_columns') }}</p>
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
                {{ t('app.landlord.integration_apis.empty_states.pivot_tables') }}
            </div>
        </div>

        <div class="flex justify-center pt-1">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                @click="addRow"
            >
                <Plus class="size-3.5" />
                {{ t('app.landlord.integration_apis.actions.add_pivot') }}
            </button>
        </div>
    </div>
</template>
