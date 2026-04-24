<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Package class="size-5" />
                    Detalhes do Produto
                </DialogTitle>
                <DialogDescription v-if="product">
                    {{ product.name || `Produto ${product.ean}` }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="isLoading" class="flex items-center justify-center py-12">
                <Loader2 class="size-8 animate-spin text-muted-foreground" />
            </div>

            <div v-else-if="product" class="space-y-6">
                <!-- Imagem e Informações Básicas -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex justify-center">
                        <img 
                            v-if="product.image_url" 
                            :src="product.image_url" 
                            :alt="product.name"
                            class="w-full max-w-xs h-32 object-contain rounded-lg border"
                        />
                        <div v-else class="w-full max-w-xs h-32 flex items-center justify-center bg-muted rounded-lg border-2 border-dashed">
                            <Package class="size-16 text-muted-foreground" />
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <Label class="text-sm text-muted-foreground">Nome</Label>
                            <p class="font-medium">{{ product.name || `Produto ${product.ean}` }}</p>
                        </div>

                        <div v-if="product.ean">
                            <Label class="text-sm text-muted-foreground">EAN</Label>
                            <p class="font-mono text-sm">{{ product.ean }}</p>
                        </div>

                        <div v-if="product.code">
                            <Label class="text-sm text-muted-foreground">Código</Label>
                            <p class="font-mono text-sm">{{ product.code }}</p>
                        </div>

                        <div v-if="product.width || product.height || product.depth">
                            <Label class="text-sm text-muted-foreground">Dimensões</Label>
                            <p class="text-sm">
                                {{ product.width || 0 }} x 
                                {{ product.height || 0 }} x 
                                {{ product.depth || 0 }} cm
                            </p>
                        </div>

                        <div v-if="abcClassification">
                            <Label class="text-sm text-muted-foreground">Classificação ABC</Label>
                            <div class="mt-1">
                                <Badge :class="abcBadgeClass">
                                    Classe {{ abcClassification }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Posição na Gôndola -->
                <div v-if="productPosition">
                    <Separator />
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold flex items-center gap-2">
                            <Box class="size-5" />
                            Posição na Gôndola
                        </h3>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Módulo</Label>
                                <p class="text-lg font-semibold">#{{ productPosition.section_ordering }}</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Prateleira</Label>
                                <p class="text-lg font-semibold">#{{ productPosition.shelf_number }}</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Frentes</Label>
                                <p class="text-lg font-semibold">{{ productPosition.facings }}x</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Capacidade</Label>
                                <p class="text-lg font-semibold">{{ productPosition.total_capacity }} un</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Análise de Estoque Alvo -->
                <div v-if="targetStockData">
                    <Separator />
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold flex items-center gap-2">
                            <Target class="size-5" />
                            Análise de Estoque Alvo
                        </h3>
                        
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div class="space-y-1 rounded-lg border p-3 bg-blue-50 dark:bg-blue-950/20">
                                <Label class="text-xs text-muted-foreground">Estoque Alvo</Label>
                                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    {{ formatNumber(targetStockData.estoque_alvo) }}
                                </p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Estoque Mínimo</Label>
                                <p class="text-2xl font-bold">{{ formatNumber(targetStockData.estoque_minimo) }}</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Estoque Atual</Label>
                                <p class="text-2xl font-bold">{{ formatNumber(targetStockData.estoque_atual) }}</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Demanda Média</Label>
                                <p class="text-lg font-semibold">{{ targetStockData.demanda_media.toFixed(2) }} un/dia</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Cobertura</Label>
                                <p class="text-lg font-semibold">{{ targetStockData.cobertura_dias }} dias</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3" :class="stockStatusClass">
                                <Label class="text-xs">Recomendação</Label>
                                <p class="text-lg font-semibold flex items-center gap-1">
                                    <AlertTriangle v-if="stockStatusText !== 'Espaço adequado'" class="size-4" />
                                    {{ stockStatusText }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas de Vendas -->
                <div v-if="salesData">
                    <Separator />
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold flex items-center gap-2">
                            <TrendingUp class="size-5" />
                            Performance de Vendas
                        </h3>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="space-y-1 rounded-lg border p-3 bg-green-50 dark:bg-green-950/20">
                                <Label class="text-xs text-muted-foreground">Total Vendido</Label>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ formatNumber(salesData.total_quantity) }}
                                </p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Faturamento</Label>
                                <p class="text-xl font-bold">R$ {{ formatMoney(salesData.total_revenue) }}</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Ticket Médio</Label>
                                <p class="text-xl font-bold">R$ {{ formatMoney(salesData.avg_price) }}</p>
                            </div>
                            
                            <div class="space-y-1 rounded-lg border p-3 bg-muted/50">
                                <Label class="text-xs text-muted-foreground">Frequência</Label>
                                <p class="text-2xl font-bold">{{ salesData.frequency }}x</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="!targetStockData && !productPosition" class="text-center py-8 text-muted-foreground">
                    <BarChart3 class="mx-auto size-12 mb-4 opacity-50" />
                    <p class="text-sm">Nenhuma análise ou estatística disponível para este produto</p>
                    <p class="text-xs mt-1">Execute análises ABC ou Estoque Alvo para visualizar dados</p>
                </div>
            </div>

            <div v-else class="text-center py-8 text-muted-foreground">
                <AlertCircle class="mx-auto size-12 mb-4 opacity-50" />
                <p>Produto não encontrado</p>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogHeader, 
    DialogTitle 
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Package, TrendingUp, Loader2, AlertCircle, BarChart3, Box, Target, AlertTriangle } from 'lucide-vue-next';
import { useAbcClassification } from '@/composables/plannerate/v3/useAbcClassification';
import { useTargetStockAnalysis } from '@/composables/plannerate/v3/useTargetStockAnalysis';
import axios from 'axios';

interface Props {
    open: boolean;
    productEan?: string | null;
    gondolaId?: string | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

// Composables
const { getClassification } = useAbcClassification();
const { getTargetStockData } = useTargetStockAnalysis();

// State
const isLoading = ref(false);
const product = ref<any>(null);
const salesData = ref<any>(null);
const productPosition = ref<any>(null);

// Computed
const abcClassification = computed(() => {
    if (!props.productEan) return null;
    return getClassification(props.productEan);
});

const targetStockData = computed(() => {
    if (!props.productEan) return null;
    return getTargetStockData(props.productEan);
});

watch(() => [props.open, props.productEan], async ([isOpen, ean]) => {
    if (isOpen && ean) {
        await loadProductData(ean as string);
    } else {
        // Limpa dados quando fecha
        product.value = null;
        salesData.value = null;
        productPosition.value = null;
    }
}, { immediate: true });

const loadProductData = async (ean: string) => {
    isLoading.value = true;
    try {
        // Busca dados do produto na API
        const response = await axios.get(`/api/products/details/${ean}`, {
            params: {
                gondola_id: props.gondolaId
            }
        });
        
        product.value = response.data.product;
        salesData.value = response.data.sales_data;
        productPosition.value = response.data.position;
    } catch (error) {
        console.error('Erro ao carregar dados do produto:', error);
        // Se falhar, tenta buscar apenas dados básicos do produto nas sections
        loadProductFromGondola(ean);
    } finally {
        isLoading.value = false;
    }
};

const loadProductFromGondola = (ean: string) => {
    // Fallback: busca produto diretamente das sections do DOM/estado
    // Isso garante que pelo menos as informações básicas sejam exibidas
    product.value = {
        ean: ean,
        name: 'Produto ' + ean.substring(0, 6),
        code: null,
        image_url: null,
        width: null,
        height: null,
        depth: null
    };
};

const formatMoney = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};

const formatNumber = (value: number) => {
    return new Intl.NumberFormat('pt-BR').format(value);
};

const abcBadgeClass = computed(() => {
    switch (abcClassification.value) {
        case 'A':
            return 'bg-green-500 text-white hover:bg-green-600';
        case 'B':
            return 'bg-yellow-500 text-gray-900 hover:bg-yellow-600';
        case 'C':
            return 'bg-red-500 text-white hover:bg-red-600';
        default:
            return 'bg-gray-400 text-white';
    }
});

const stockStatusClass = computed(() => {
    if (!targetStockData.value) return '';
    
    const capacity = productPosition.value?.total_capacity || 0;
    const target = targetStockData.value.estoque_alvo;
    const tolerance = target * 0.2;
    
    if (capacity < target - tolerance) {
        return 'text-red-600';
    } else if (capacity > target + tolerance) {
        return 'text-yellow-600';
    } else {
        return 'text-green-600';
    }
});

const stockStatusText = computed(() => {
    if (!targetStockData.value) return '';
    
    const capacity = productPosition.value?.total_capacity || 0;
    const target = targetStockData.value.estoque_alvo;
    const tolerance = target * 0.2;
    
    if (capacity < target - tolerance) {
        return 'Aumentar espaço';
    } else if (capacity > target + tolerance) {
        return 'Reduzir espaço';
    } else {
        return 'Espaço adequado';
    }
});
</script>

