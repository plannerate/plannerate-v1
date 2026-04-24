<script setup lang="ts">
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
import { Calculator, Settings } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AnalysisPeriodSelector from './AnalysisPeriodSelector.vue';

interface FormData {
    table_type: 'sales' | 'monthly_summaries';
    date_from: string;
    date_to: string;
    start_month: string;
    end_month: string;
    nivel_servico_a: number;
    nivel_servico_b: number;
    nivel_servico_c: number;
    cobertura_dias_a: number;
    cobertura_dias_b: number;
    cobertura_dias_c: number;
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
    nivel_servico_a: props.initialData?.nivel_servico_a ?? 0.7,
    nivel_servico_b: props.initialData?.nivel_servico_b ?? 0.8,
    nivel_servico_c: props.initialData?.nivel_servico_c ?? 0.9,
    cobertura_dias_a: props.initialData?.cobertura_dias_a ?? 2,
    cobertura_dias_b: props.initialData?.cobertura_dias_b ?? 5,
    cobertura_dias_c: props.initialData?.cobertura_dias_c ?? 7,
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
            nivel_servico_a: newData.nivel_servico_a ?? 0.7,
            nivel_servico_b: newData.nivel_servico_b ?? 0.8,
            nivel_servico_c: newData.nivel_servico_c ?? 0.9,
            cobertura_dias_a: newData.cobertura_dias_a ?? 2,
            cobertura_dias_b: newData.cobertura_dias_b ?? 5,
            cobertura_dias_c: newData.cobertura_dias_c ?? 7,
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

const tableTypeLabel = computed(() => {
    return form.value.table_type === 'sales' ? 'Vendas' : 'Resumo Mensal';
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="z-[600] max-h-[88vh] max-w-2xl overflow-hidden p-0">
            <DialogHeader class="border-b border-border bg-background px-5 py-3">
                <DialogTitle class="flex items-center gap-2 text-base">
                    <Settings class="size-4 text-muted-foreground" />
                    Parâmetros de Estoque Alvo
                </DialogTitle>
                <DialogDescription class="text-xs">
                    Configure os níveis de serviço e parâmetros de reposição para calcular o estoque ideal.
                </DialogDescription>
                <div class="inline-flex w-fit items-center rounded-md border border-border bg-accent/40 px-2 py-1 text-[11px] text-foreground">
                    Tipo atual: {{ tableTypeLabel }}
                </div>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-3 overflow-y-auto px-5 py-4">
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

                <!-- Níveis de Serviço -->
                <div class="space-y-1.5">
                    <Label class="text-xs">Níveis de Serviço</Label>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Classe A</Label>
                            <Input
                                v-model.number="form.nivel_servico_a"
                                type="number"
                                step="0.1"
                                min="0.5"
                                max="0.99"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Classe B</Label>
                            <Input
                                v-model.number="form.nivel_servico_b"
                                type="number"
                                step="0.1"
                                min="0.5"
                                max="0.99"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Classe C</Label>
                            <Input
                                v-model.number="form.nivel_servico_c"
                                type="number"
                                step="0.1"
                                min="0.5"
                                max="0.99"
                                class="h-8 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <!-- Dias de Cobertura -->
                <div class="space-y-1.5">
                    <Label class="text-xs">Dias de Cobertura</Label>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Classe A</Label>
                            <Input
                                v-model.number="form.cobertura_dias_a"
                                type="number"
                                step="1"
                                min="1"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Classe B</Label>
                            <Input
                                v-model.number="form.cobertura_dias_b"
                                type="number"
                                step="1"
                                min="1"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">Classe C</Label>
                            <Input
                                v-model.number="form.cobertura_dias_c"
                                type="number"
                                step="1"
                                min="1"
                                class="h-8 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <Button type="submit" class="h-9 w-full">
                    <Calculator class="mr-2 size-4" />
                    Executar Cálculo
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

