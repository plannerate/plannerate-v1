<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Filter } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface FiltersForm {
    table_type: 'sales' | 'monthly_summaries';
    category_id: string;
    eans: string[];
    eanInput: string;
    date_from: string;
    date_to: string;
    month_from: string;
    month_to: string;
}

interface Props {
    modelValue: FiltersForm;
    errors?: Record<string, string>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: FiltersForm];
}>();

const eansExpanded = ref(false);

const form = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const addEan = () => {
    if (form.value.eanInput.trim() && !form.value.eans.includes(form.value.eanInput.trim())) {
        form.value = {
            ...form.value,
            eans: [...form.value.eans, form.value.eanInput.trim()],
            eanInput: '',
        };
    }
};

const removeEan = (index: number) => {
    const newEans = [...form.value.eans];
    newEans.splice(index, 1);
    form.value = {
        ...form.value,
        eans: newEans,
    };
};
</script>

<template>
    <div class="space-y-6">
        <!-- Tipo de Tabela -->
        <div class="space-y-2">
            <label class="text-sm font-medium">Tipo de Tabela</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.table_type"
                        type="radio"
                        value="sales"
                        class="rounded"
                    />
                    <span>Vendas</span>
                </label>
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.table_type"
                        type="radio"
                        value="monthly_summaries"
                        class="rounded"
                    />
                    <span>Resumo Mensal</span>
                </label>
            </div>
        </div>

        <!-- Categoria ou EANs -->
        <div class="space-y-4">
            <div class="space-y-2">
                <label class="text-sm font-medium">Categoria</label>
                <Input
                    v-model="form.category_id"
                    placeholder="ID da categoria"
                    :disabled="form.eans.length > 0"
                    :class="errors?.category_id ? 'border-destructive' : ''"
                />
                <p v-if="errors?.category_id" class="text-sm text-destructive">
                    {{ errors.category_id }}
                </p>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium"
                    >EANs (alternativa à categoria)</label
                >
                <div class="flex gap-2">
                    <Input
                        v-model="form.eanInput"
                        placeholder="Digite um EAN e pressione Enter"
                        :disabled="!!form.category_id"
                        :class="errors?.eans ? 'border-destructive' : ''"
                        @keydown.enter.prevent="addEan"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        @click="addEan"
                        :disabled="!!form.category_id"
                    >
                        Adicionar
                    </Button>
                </div>
                <p v-if="errors?.eans" class="text-sm text-destructive">
                    {{ errors.eans }}
                </p>
                <div v-if="form.eans.length > 0" class="mt-2 space-y-2">
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="(ean, index) in (eansExpanded
                                ? form.eans
                                : form.eans.slice(0, 5))"
                            :key="index"
                            variant="secondary"
                            class="flex items-center gap-1"
                        >
                            {{ ean }}
                            <button
                                type="button"
                                @click="removeEan(eansExpanded ? index : index)"
                                class="ml-1 hover:text-destructive"
                            >
                                ×
                            </button>
                        </Badge>
                    </div>
                    <Button
                        v-if="form.eans.length > 5"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-xs text-muted-foreground"
                        @click="eansExpanded = !eansExpanded"
                    >
                        {{
                            eansExpanded
                                ? 'Mostrar menos'
                                : `Mostrar mais (${form.eans.length - 5} restantes)`
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Filtros de Período -->
        <div v-if="form.table_type === 'sales'" class="space-y-4">
            <div class="space-y-2">
                <label class="text-sm font-medium flex items-center gap-2">
                    <Filter class="size-4" />
                    Período de Vendas
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-xs text-muted-foreground"
                            >Data Inicial</label
                        >
                        <Input
                            v-model="form.date_from"
                            type="date"
                            placeholder="DD/MM/AAAA"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs text-muted-foreground"
                            >Data Final</label
                        >
                        <Input
                            v-model="form.date_to"
                            type="date"
                            placeholder="DD/MM/AAAA"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="form.table_type === 'monthly_summaries'"
            class="space-y-4"
        >
            <div class="space-y-2">
                <label class="text-sm font-medium flex items-center gap-2">
                    <Filter class="size-4" />
                    Período Mensal
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-xs text-muted-foreground"
                            >Mês Inicial</label
                        >
                        <Input
                            v-model="form.month_from"
                            type="month"
                            placeholder="YYYY-MM"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs text-muted-foreground"
                            >Mês Final</label
                        >
                        <Input
                            v-model="form.month_to"
                            type="month"
                            placeholder="YYYY-MM"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

