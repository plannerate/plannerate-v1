<script setup lang="ts">
import { ref, computed, provide } from 'vue';
import { Head } from '@inertiajs/vue3';
import Sections from '@/components/plannerate/client/Sections.vue';
import Performance from '@/components/plannerate/v3/header/Performance.vue';
import ProductDetailsModal from '@/components/plannerate/client/ProductDetailsModal.vue';
import ShareQRCodeModal from '@/components/plannerate/v3/header/ShareQRCodeModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ZoomIn, ZoomOut, Maximize2, Download, QrCode, Info, Gauge, ChevronDown, Eye, EyeOff, Trash2, X } from 'lucide-vue-next';
import { usePerformanceIndicators } from '@/composables/plannerate/v3/usePerformanceIndicators';

// Props
interface Props {
    gondola: any;
    statistics: {
        total_products: number;
        total_facings: number;
        occupancy_rate: number;
        total_sections: number;
        empty_segments: number;
        total_space: number;
        occupied_space: number;
    };
    readOnly: boolean;
}

const props = defineProps<Props>();

// Provide read-only context for child components
provide('readOnly', true);
provide('isClientView', true);

// State
const zoom = ref(1);
const showStats = ref(false);
const showPerformanceModal = ref(false);
const showProductDetailsModal = ref(false);
const showShareQRModal = ref(false);
const selectedProductEan = ref<string | null>(null);
const scale = computed(() => 3 * zoom.value); // Base scale é 3 (igual ao V3)

// Performance indicators
const performance = usePerformanceIndicators();

// Computed
const clientName = computed(() => props.gondola.planogram?.client?.name ?? 'Cliente não informado');
const storeName = computed(() => props.gondola.planogram?.store?.name ?? 'Loja não informada');
const gondolaTitle = computed(() => props.gondola.name || 'Gôndola sem nome');
const sortedSections = computed(() => {
    return [...(props.gondola.sections || [])].sort((a, b) => a.ordering - b.ordering);
});

// Methods
const zoomIn = () => {
    zoom.value = Math.min(zoom.value + 0.1, 2);
};

const zoomOut = () => {
    zoom.value = Math.max(zoom.value - 0.1, 0.5);
};

const resetZoom = () => {
    zoom.value = 1;
};

const downloadPDF = () => {
    window.open(`/export/gondola/${props.gondola.id}/pdf`, '_blank');
};

const handleProductClick = (ean: string) => {
    selectedProductEan.value = ean;
    showProductDetailsModal.value = true;
};
</script>

<template>
    <div class="fixed inset-0 flex flex-col bg-background overflow-hidden">
        <Head :title="`${gondolaTitle} - Visualização Client`" />

        <!-- Header -->
        <div class="shrink-0 border-b bg-card z-10">
            <div class="px-4 py-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-foreground">
                            {{ gondolaTitle }}
                        </h1>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ clientName }} • {{ storeName }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">
                            <Info class="mr-1 size-3" />
                            Somente Leitura
                        </Badge>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="shrink-0 border-b bg-card z-10">
            <div class="px-4 py-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" @click="zoomOut">
                            <ActionIconBox variant="outline">
                                <ZoomOut />
                            </ActionIconBox>
                        </Button>
                        <span class="text-sm text-muted-foreground min-w-[60px] text-center">
                            {{ Math.round(zoom * 100) }}%
                        </span>
                        <Button variant="outline" size="sm" @click="zoomIn">
                            <ActionIconBox variant="outline">
                                <ZoomIn />
                            </ActionIconBox>
                        </Button>
                        <Button variant="outline" size="sm" @click="resetZoom">
                            <ActionIconBox variant="outline">
                                <Maximize2 />
                            </ActionIconBox>
                        </Button>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Dropdown Performance -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    <ActionIconBox variant="outline">
                                        <Gauge />
                                    </ActionIconBox>
                                    Performance
                                    <ChevronDown class="ml-1 size-3" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-64 z-[9999]">
                                <DropdownMenuItem @click="showPerformanceModal = true">
                                    <Gauge class="mr-2 size-4" />
                                    Abrir Análises
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                
                                <!-- Controles Individuais -->
                                <DropdownMenuItem 
                                    @click="performance.abc.toggleVisibility()"
                                    :disabled="!performance.abc.hasData.value"
                                >
                                    <Eye v-if="!performance.abc.isVisible.value" class="mr-2 size-4 text-green-600" />
                                    <EyeOff v-else class="mr-2 size-4 text-green-600" />
                                    {{ performance.abc.isVisible.value ? 'Esconder' : 'Mostrar' }} ABC
                                    <span class="ml-auto text-xs text-muted-foreground">
                                        ({{ performance.abc.stats.value.total }})
                                    </span>
                                </DropdownMenuItem>
                                
                                <DropdownMenuItem 
                                    @click="performance.targetStock.toggleVisibility()"
                                    :disabled="!performance.targetStock.hasData.value"
                                >
                                    <Eye v-if="!performance.targetStock.isVisible.value" class="mr-2 size-4 text-blue-600" />
                                    <EyeOff v-else class="mr-2 size-4 text-blue-600" />
                                    {{ performance.targetStock.isVisible.value ? 'Esconder' : 'Mostrar' }} Estoque Alvo
                                    <span class="ml-auto text-xs text-muted-foreground">
                                        ({{ performance.targetStock.stats.value.total }})
                                    </span>
                                </DropdownMenuItem>
                                
                                <DropdownMenuSeparator />
                                
                                <!-- Controle Geral -->
                                <DropdownMenuItem 
                                    @click="performance.toggleAllIndicators()"
                                    :disabled="!performance.hasAnyData.value"
                                >
                                    <Eye v-if="!performance.anyVisible.value" class="mr-2 size-4" />
                                    <EyeOff v-else class="mr-2 size-4" />
                                    {{ performance.anyVisible.value ? 'Esconder' : 'Mostrar' }} Todos
                                </DropdownMenuItem>
                                
                                <DropdownMenuSeparator />
                                
                                <DropdownMenuItem 
                                    @click="performance.clearAllAnalysis(gondola.id)"
                                    :disabled="!performance.hasAnyData.value"
                                    class="text-destructive"
                                >
                                    <Trash2 class="mr-2 size-4" />
                                    Limpar Todas as Análises
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button variant="outline" size="sm" @click="showStats = !showStats">
                            <ActionIconBox variant="outline">
                                <Info />
                            </ActionIconBox>
                            {{ showStats ? 'Ocultar' : 'Mostrar' }} Estatísticas
                        </Button>
                        <Button variant="outline" size="sm" @click="showShareQRModal = true">
                            <ActionIconBox variant="outline">
                                <QrCode />
                            </ActionIconBox>
                            QR Code
                        </Button>
                        <Button variant="default" size="sm" @click="downloadPDF">
                            <ActionIconBox variant="default">
                                <Download />
                            </ActionIconBox>
                            Baixar PDF
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content - Fullscreen -->
        <div class="flex-1 relative overflow-hidden">
            <!-- Gondola Canvas - Fullscreen -->
            <div class="absolute inset-0 overflow-auto bg-muted/20 p-8">
                <div
                    v-if="sortedSections.length > 0"
                    class="flex min-h-full items-end pt-12 pb-4"
                >
                    <Sections
                        :sections="sortedSections"
                        :scale="scale"
                        @product-click="handleProductClick"
                    />
                </div>
                <div v-else class="flex h-full items-center justify-center text-center text-muted-foreground">
                    <div>
                        <Info class="size-12 mx-auto mb-4 opacity-50" />
                        <p>Nenhuma seção encontrada nesta gôndola</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Sidebar - Overlay Fixed -->
            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-300 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div
                    v-if="showStats"
                    class="fixed top-0 right-0 h-full w-80 bg-background border-l border-border shadow-xl z-50 flex flex-col"
                >
                    <!-- Sidebar Header -->
                    <div class="shrink-0 flex items-center justify-between border-b border-border px-4 py-3">
                        <CardTitle class="text-lg">Estatísticas</CardTitle>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            @click="showStats = false"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <!-- Sidebar Content -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <div class="space-y-4">
                            <!-- Total de Produtos -->
                            <div>
                                <div class="text-sm font-medium text-muted-foreground">
                                    Total de Produtos
                                </div>
                                <div class="text-2xl font-bold">
                                    {{ statistics.total_products }}
                                </div>
                            </div>

                            <!-- Total de Facings -->
                            <div>
                                <div class="text-sm font-medium text-muted-foreground">
                                    Total de Facings
                                </div>
                                <div class="text-2xl font-bold">
                                    {{ statistics.total_facings }}
                                </div>
                            </div>

                            <!-- Taxa de Ocupação -->
                            <div>
                                <div class="text-sm font-medium text-muted-foreground">
                                    Taxa de Ocupação
                                </div>
                                <div class="text-2xl font-bold">
                                    {{ statistics.occupancy_rate }}%
                                </div>
                                <div class="text-xs text-muted-foreground mt-1">
                                    {{ statistics.occupied_space.toFixed(0) }} cm² / {{ statistics.total_space }} cm²
                                </div>
                            </div>

                            <!-- Total de Módulos -->
                            <div>
                                <div class="text-sm font-medium text-muted-foreground">
                                    Total de Módulos
                                </div>
                                <div class="text-2xl font-bold">
                                    {{ statistics.total_sections }}
                                </div>
                            </div>

                            <!-- Segmentos Vazios -->
                            <div v-if="statistics.empty_segments > 0">
                                <div class="text-sm font-medium text-muted-foreground">
                                    Segmentos Vazios
                                </div>
                                <div class="text-2xl font-bold text-destructive">
                                    {{ statistics.empty_segments }}
                                </div>
                            </div>

                            <!-- Dimensões -->
                            <div class="pt-4 border-t">
                                <div class="text-sm font-medium text-muted-foreground mb-2">
                                    Dimensões
                                </div>
                                <div class="text-sm space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Largura:</span>
                                        <span class="font-medium">{{ gondola.width }} cm</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Altura:</span>
                                        <span class="font-medium">{{ gondola.height }} cm</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Profundidade:</span>
                                        <span class="font-medium">{{ gondola.depth }} cm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Performance Modal -->
        <Performance
            v-model:open="showPerformanceModal"
            :gondola-id="gondola.id"
            :planogram="gondola.planogram"
        />

        <!-- Product Details Modal -->
        <ProductDetailsModal
            v-model:open="showProductDetailsModal"
            :product-ean="selectedProductEan"
            :gondola-id="gondola.id"
        />

        <!-- Share QR Code Modal -->
        <ShareQRCodeModal
            v-model:open="showShareQRModal"
            :gondola-id="gondola.id"
            :gondola-name="gondola.name"
        />
    </div>
</template>

