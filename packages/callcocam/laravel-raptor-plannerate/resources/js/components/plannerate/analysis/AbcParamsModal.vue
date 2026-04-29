<script setup lang="ts">
import { Calculator } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AnalysisPeriodSelector from './AnalysisPeriodSelector.vue';

interface FormData {
    table_type: 'sales' | 'monthly_summaries';
    date_from: string;
    date_to: string;
    start_month: string;
    end_month: string;
    peso_qtde: number;
    peso_valor: number;
    peso_margem: number;
    corte_a: number;
    corte_b: number;
}

interface Props {
    open: boolean;
    initialData?: Partial<FormData> | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'submit', data: FormData): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    initialData: null,
});

const emit = defineEmits<Emits>();

// Form state
const form = ref<FormData>({
    table_type: props.initialData?.table_type || 'sales',
    date_from: props.initialData?.date_from || '',
    date_to: props.initialData?.date_to || '',
    start_month: props.initialData?.start_month || '',
    end_month: props.initialData?.end_month || '',
    peso_qtde: props.initialData?.peso_qtde ?? 0.3,
    peso_valor: props.initialData?.peso_valor ?? 0.3,
    peso_margem: props.initialData?.peso_margem ?? 0.4,
    corte_a: props.initialData?.corte_a ?? 0.8,
    corte_b: props.initialData?.corte_b ?? 0.85,
});

// Sincroniza form com initialData quando mudar
watch(() => props.initialData, (newData) => {
    if (newData) {
        form.value = {
            table_type: newData.table_type || 'sales',
            date_from: newData.date_from || '',
            date_to: newData.date_to || '',
            start_month: newData.start_month || '',
            end_month: newData.end_month || '',
            peso_qtde: newData.peso_qtde ?? 0.3,
            peso_valor: newData.peso_valor ?? 0.3,
            peso_margem: newData.peso_margem ?? 0.4,
            corte_a: newData.corte_a ?? 0.8,
            corte_b: newData.corte_b ?? 0.85,
        };
    }
}, { deep: true, immediate: true });

const handleSubmit = () => {
    emit('submit', form.value);
    emit('update:open', false);
};

const handleOpenChange = (value: boolean) => {
    emit('update:open', value);
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto z-[600]">
            <DialogHeader class="pb-3">
                <DialogTitle class="text-base">Parâmetros de Análise de Assortimento</DialogTitle>
                <DialogDescription class="text-xs">
                    Configure os parâmetros de datas, pesos e cortes para a análise de assortimento.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Seletor de Período Compartilhado -->
                <AnalysisPeriodSelector
                    :table-type="form.table_type"
                    :date-from="form.date_from"
                    :date-to="form.date_to"
                    :start-month="form.start_month"
                    :end-month="form.end_month"
                    @update:table-type="form.table_type = $event"
                    @update:date-from="form.date_from = $event"
                    @update:date-to="form.date_to = $event"
                    @update:start-month="form.start_month = $event"
                    @update:end-month="form.end_month = $event"
                />
                <!-- Pesos -->
                <div class="space-y-2">
                    <Label class="text-xs">Pesos da Média Ponderada</Label>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Peso Quantidade</Label>
                            <Input
                                v-model.number="form.peso_qtde"
                                type="number"
                                step="0.1"
                                min="0"
                                max="1"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Peso Valor</Label>
                            <Input
                                v-model.number="form.peso_valor"
                                type="number"
                                step="0.1"
                                min="0"
                                max="1"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Peso Margem</Label>
                            <Input
                                v-model.number="form.peso_margem"
                                type="number"
                                step="0.1"
                                min="0"
                                max="1"
                                class="h-8 text-xs"
                            />
                        </div>
                    </div>
                    <p class="text-[10px] text-muted-foreground">
                        A soma dos pesos deve ser igual a 1.0
                    </p>
                </div>

                <!-- Limites de Classificação -->
                <div class="space-y-2">
                    <Label class="text-xs">Limites de Classificação (%)</Label>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Limite Classe A</Label>
                            <Input
                                v-model.number="form.corte_a"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Limite Classe B</Label>
                            <Input
                                v-model.number="form.corte_b"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1"
                                class="h-8 text-xs"
                            />
                        </div>
                    </div>
                    <p class="text-[10px] text-muted-foreground">
                        Produtos até o limite A são classe A, entre A e B são classe B, e acima de B são classe C.
                    </p>
                </div>

                <Button type="submit" class="w-full">
                    <Calculator class="mr-2 size-4" />
                    Executar Análise
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

