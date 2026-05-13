<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ValidationRow } from './types';

const props = defineProps<{
    modelValue: ValidationRow[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: ValidationRow[]];
}>();

function newRow(): ValidationRow {
    return {
        id: `validation-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        type: 'any_of',
        sources: '',
        allowed_values: 'S',
    };
}

function addRow(): void {
    emit('update:modelValue', [...props.modelValue, newRow()]);
}

function updateRow(index: number, patch: Partial<ValidationRow>): void {
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
        <p class="text-sm font-medium text-muted-foreground">Validações de grupo</p>

        <div class="space-y-2">
            <div
                v-for="(row, index) in props.modelValue"
                :key="row.id"
                class="grid grid-cols-1 gap-3 rounded-md border border-border bg-background p-3 md:grid-cols-12"
            >
                <div class="grid gap-2 md:col-span-2">
                    <Label :for="`validation-type-${row.id}`">Tipo</Label>
                    <select
                        :id="`validation-type-${row.id}`"
                        :value="row.type"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                        @change="updateRow(index, { type: ($event.target as HTMLSelectElement).value })"
                    >
                        <option value="all_of">all_of — todos devem passar</option>
                    <option value="any_of">any_of — pelo menos 1</option>
                    </select>
                </div>

                <div class="grid gap-2 md:col-span-6">
                    <Label :for="`validation-sources-${row.id}`">
                        Campos da API
                        <span class="ml-1 text-xs font-normal text-muted-foreground">(vírgula separando)</span>
                    </Label>
                    <Input
                        :id="`validation-sources-${row.id}`"
                        :model-value="row.sources"
                        placeholder="cadastro_ativo, pertence_ao_mix, ativo_erp"
                        @update:model-value="updateRow(index, { sources: String($event) })"
                    />
                </div>

                <div class="grid gap-2 md:col-span-3">
                    <Label :for="`validation-allowed-${row.id}`">
                        Valores permitidos
                        <span class="ml-1 text-xs font-normal text-muted-foreground">(vírgula separando)</span>
                    </Label>
                    <Input
                        :id="`validation-allowed-${row.id}`"
                        :model-value="row.allowed_values"
                        placeholder="S"
                        @update:model-value="updateRow(index, { allowed_values: String($event) })"
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

                <div v-if="row.sources.trim() !== ''" class="md:col-span-12">
                    <p class="text-xs text-muted-foreground">
                        <template v-if="row.type === 'all_of'">
                            Rejeita o item se <strong>qualquer</strong> campo de
                        </template>
                        <template v-else>
                            Rejeita o item se <strong>nenhum</strong> campo de
                        </template>
                        <span class="font-mono text-foreground">{{ row.sources }}</span>
                        <template v-if="row.type === 'all_of'"> não tiver</template>
                        <template v-else> tiver</template>
                        valor igual a
                        <span class="font-mono text-foreground">{{ row.allowed_values || 'S' }}</span>.
                    </p>
                </div>
            </div>

            <div
                v-if="props.modelValue.length === 0"
                class="rounded-md border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground"
            >
                Nenhuma validação de grupo configurada.
            </div>
        </div>

        <div class="flex justify-center pt-1">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                @click="addRow"
            >
                <Plus class="size-3.5" />
                Adicionar validação
            </button>
        </div>
    </div>
</template>
