<script setup lang="ts">
import { Trash2, Plus } from 'lucide-vue-next';

type Row = {
    key: string;
    value: string;
    enabled: boolean;
};

const props = defineProps<{
    name: string;
    modelValue: Row[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Row[]];
}>();

function addRow(): void {
    emit('update:modelValue', [...props.modelValue, { key: '', value: '', enabled: true }]);
}

function removeRow(index: number): void {
    const updated = props.modelValue.filter((_, i) => i !== index);
    emit('update:modelValue', updated);
}

function updateRow(index: number, field: keyof Row, value: string | boolean): void {
    const updated = props.modelValue.map((row, i) =>
        i === index ? { ...row, [field]: value } : row,
    );
    emit('update:modelValue', updated);
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="w-8 px-3 py-2" />
                    <th class="px-3 py-2 text-left font-medium text-muted-foreground">Key</th>
                    <th class="px-3 py-2 text-left font-medium text-muted-foreground">Value</th>
                    <th class="w-10 px-3 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, i) in modelValue"
                    :key="i"
                    class="border-b border-border/50 last:border-0"
                    :class="!row.enabled ? 'opacity-50' : ''"
                >
                    <td class="px-3 py-2">
                        <input type="hidden" :name="`${name}[${i}][enabled]`" value="0" />
                        <input
                            type="checkbox"
                            :name="`${name}[${i}][enabled]`"
                            value="1"
                            :checked="row.enabled"
                            class="accent-primary"
                            @change="updateRow(i, 'enabled', ($event.target as HTMLInputElement).checked)"
                        />
                    </td>
                    <td class="px-2 py-1.5">
                        <input
                            type="text"
                            :name="`${name}[${i}][key]`"
                            :value="row.key"
                            placeholder="Key"
                            class="h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            @input="updateRow(i, 'key', ($event.target as HTMLInputElement).value)"
                        />
                    </td>
                    <td class="px-2 py-1.5">
                        <input
                            type="text"
                            :name="`${name}[${i}][value]`"
                            :value="row.value"
                            placeholder="Value"
                            class="h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            @input="updateRow(i, 'value', ($event.target as HTMLInputElement).value)"
                        />
                    </td>
                    <td class="px-2 py-1.5">
                        <button
                            type="button"
                            class="flex size-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                            @click="removeRow(i)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="modelValue.length === 0">
                    <td colspan="4" class="px-3 py-4 text-center text-sm text-muted-foreground">
                        Nenhum item. Clique em "+ Adicionar" para começar.
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="border-t border-border/50 px-3 py-2">
            <button
                type="button"
                class="flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
                @click="addRow"
            >
                <Plus class="size-3.5" />
                Adicionar
            </button>
        </div>
    </div>
</template>
