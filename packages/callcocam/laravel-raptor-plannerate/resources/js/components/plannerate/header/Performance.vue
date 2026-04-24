<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogContent
            class="max-w-[95vw] xl:max-w-[80vw] max-h-[95vh] w-full h-full overflow-hidden flex flex-col p-0 z-[500]">
            <DialogHeader class="px-4 pt-4 pb-2">
                <DialogTitle class="flex items-center gap-2 text-lg">
                    <Gauge class="size-4" />
                    Análise de Performance
                </DialogTitle>
                <DialogDescription class="text-xs">
                    Visualize métricas de ABC, Estoque Alvo e BCG para os produtos da gôndola
                </DialogDescription>
            </DialogHeader>

            <Tabs v-model="activeTab" class="flex-1 flex flex-col overflow-hidden px-4 pb-4">
                <TabsList class="grid w-full grid-cols-3 h-9">
                    <TabsTrigger value="abc">
                        <div class="flex items-center gap-1.5">
                            <BarChart3 class="size-3.5 shrink-0  " />
                            <span class="leading-tight">ABC</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger value="target-stock">
                        <div class="flex items-center gap-1.5">
                            <Package class="size-3.5 shrink-0" />
                            <span class="leading-tight">Estoque Alvo</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger value="bcg" disabled>
                        <div class="flex items-center gap-1.5">
                            <TrendingUp class="size-3.5 shrink-0" />
                            <span class="leading-tight">BCG (Em breve)</span>
                        </div>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="abc" class="flex-1 overflow-auto mt-2">
                    <PerformanceAbcTab :gondola-id="gondolaId" :planogram="planogram" :results="analysis?.abc?.results" />
                </TabsContent>

                <TabsContent value="target-stock" class="flex-1 overflow-auto mt-2">
                    <PerformanceTargetStockTab :gondola-id="gondolaId" :planogram="planogram" :results="analysis?.stock?.results" />
                </TabsContent>

                <TabsContent value="bcg" class="flex-1 overflow-auto mt-2">
                    <div class="flex items-center justify-center h-full text-muted-foreground">
                        <div class="text-center">
                            <TrendingUp class="mx-auto size-12 mb-4 opacity-50" />
                            <p class="text-lg font-medium">Análise BCG</p>
                            <p class="text-sm">Em breve</p>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BarChart3, Gauge, Package, TrendingUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import PerformanceAbcTab from './PerformanceAbcTab.vue';
import PerformanceTargetStockTab from './PerformanceTargetStockTab.vue';
import { AbcAnalysis, StockAnalysis } from '@/types/planogram';

interface Planogram {
    id: string;
    name: string;
    client_id?: string;
    start_date?: string;
    end_date?: string;
}

interface Props {
    open: boolean;
    gondolaId?: string | null;
    planogram?: Planogram | null;
    analysis?: {
        abc?: AbcAnalysis;
        stock?: StockAnalysis;
        [key: string]: any; // Permite outras análises futuras sem quebrar o componente
     };
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    gondolaId: null,
    planogram: null,
});

const emit = defineEmits<Emits>();

const isOpen = ref(props.open);
const activeTab = ref('abc'); 
// Sincroniza isOpen com o prop open quando ele mudar
watch(() => props.open, (newValue) => {
    isOpen.value = newValue;
}, { immediate: true });

const handleOpenChange = (value: boolean) => {
    isOpen.value = value;
    emit('update:open', value);
};
</script>
