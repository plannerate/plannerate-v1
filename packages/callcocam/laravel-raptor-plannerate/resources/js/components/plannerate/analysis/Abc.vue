<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    Settings,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { calculateAbc } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaAnalysisController';
import AbcParamsModal from './AbcParamsModal.vue';
import AbcResultsList from './AbcResultsList.vue';
import type { AbcResult } from '@/components/plannerate/analysis/abc/types';

interface InitialData {
    results?: AbcResult[];
    gondola?: {
        id: string;
        name: string;
        slug: string;
    };
    planogram?: {
        id: string;
        name: string;
        tenant_id?: string;
        start_date?: string;
        end_date?: string;
    };
    filters?: {
        table_type: string;
        tenant_id?: string;
        date_from?: string;
        date_to?: string;
    };
    weights?: {
        peso_qtde: number;
        peso_valor: number;
        peso_margem: number;
    };
    cuts?: {
        corte_a: number;
        corte_b: number;
    };
}

interface Props {
    initialData?: InitialData | null;
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    initialData: null,
    errors: () => ({}),
});

// Form state
const form = ref({
    table_type: (props.initialData?.filters?.table_type || 'sales') as 'sales' | 'monthly_summaries',
    date_from: props.initialData?.filters?.date_from || props.initialData?.planogram?.start_date || '',
    date_to: props.initialData?.filters?.date_to || props.initialData?.planogram?.end_date || '',
    peso_qtde: props.initialData?.weights?.peso_qtde || 0.3,
    peso_valor: props.initialData?.weights?.peso_valor || 0.3,
    peso_margem: props.initialData?.weights?.peso_margem || 0.4,
    corte_a: props.initialData?.cuts?.corte_a || 0.8,
    corte_b: props.initialData?.cuts?.corte_b || 0.85,
});

// ID da gôndola (vem do initialData)
const gondolaId = computed(() => props.initialData?.gondola?.id);

// Modal de parâmetros
const showParametersModal = ref(false);

// Results
const results = computed(() => props.initialData?.results || []);

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'Não definida';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear();
        
        return `${day}/${month}/${year}`;
    } catch {
        return dateString;
    }
};

const handleParamsSubmit = (data: typeof form.value) => {
    if (!gondolaId.value) {
        console.error('ID da gôndola não encontrado');
        return;
    }

    // Atualiza o form com os dados do modal
    form.value = { ...data };

    // Constrói os parâmetros da query string
    const queryParams: Record<string, string> = {};

    if (data.table_type) {
        queryParams.table_type = data.table_type;
    }

    if (data.date_from) {
        queryParams.date_from = data.date_from;
    }

    if (data.date_to) {
        queryParams.date_to = data.date_to;
    }

    if (data.peso_qtde !== undefined) {
        queryParams.peso_qtde = data.peso_qtde.toString();
    }

    if (data.peso_valor !== undefined) {
        queryParams.peso_valor = data.peso_valor.toString();
    }

    if (data.peso_margem !== undefined) {
        queryParams.peso_margem = data.peso_margem.toString();
    }

    if (data.corte_a !== undefined) {
        queryParams.corte_a = data.corte_a.toString();
    }

    if (data.corte_b !== undefined) {
        queryParams.corte_b = data.corte_b.toString();
    }

    // Usa a action para construir a URL com query params
    const routeDefinition = calculateAbc(gondolaId.value, { query: queryParams });

    // Usa GET para a rota da gôndola
    router.visit(routeDefinition.url, {
        preserveState: false,
        preserveScroll: true,
        onError: (errors) => {
            console.error('Erros de validação:', errors);
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight">Análise ABC</h1>
            <p class="text-muted-foreground">
                Classificação de produtos por importância usando média ponderada
            </p>
        </div>

        <!-- Configuração Card -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Settings class="size-5" />
                            Parâmetros de Análise ABC
                        </CardTitle>
                        <CardDescription>
                            Configure os parâmetros para a análise ABC.
                        </CardDescription>
                    </div>
                    <Button type="button" @click="showParametersModal = true">
                        <Settings class="mr-2 size-4" />
                        Configurar Parâmetros
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-4 gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">Tipo de Tabela:</span>
                        <span class="font-medium">
                            {{ form.table_type === 'sales' ? 'Vendas (Sales)' : 'Resumo Mensal' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Calendar class="size-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Data Inicial:</span>
                        <span class="font-medium">{{ formatDate(form.date_from) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Calendar class="size-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Data Final:</span>
                        <span class="font-medium">{{ formatDate(form.date_to) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">Pesos:</span>
                        <span class="font-medium">
                            Q:{{ form.peso_qtde }} V:{{ form.peso_valor }} M:{{ form.peso_margem }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Modal de Parâmetros -->
        <AbcParamsModal
            v-model:open="showParametersModal"
            :initial-data="form"
            @submit="handleParamsSubmit"
        />

        <!-- Resultados -->
        <AbcResultsList v-if="results.length > 0" :results="results" />

        <!-- Empty State -->
        <Card v-else>
            <CardContent class="pt-6">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <AlertCircle class="mb-4 size-12 text-muted-foreground" />
                    <h3 class="mb-2 text-lg font-semibold">Nenhum resultado</h3>
                    <p class="text-sm text-muted-foreground">
                        Configure os parâmetros acima e execute a análise para ver os
                        resultados.
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
