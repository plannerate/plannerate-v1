<script setup lang="ts">
import { ref, computed, provide } from 'vue';
import { Head } from '@inertiajs/vue3';
import Sections from '@/components/plannerate/client/Sections.vue';
import ProductDetailsModal from '@/components/plannerate/client/ProductDetailsModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { ZoomIn, ZoomOut, Maximize2, ArrowLeft, Info, X } from 'lucide-vue-next';

interface Props {
    gondola: any;
    section: any;
    readOnly: boolean;
}

const props = defineProps<Props>();

provide('readOnly', true);
provide('isClientView', true);

const zoom = ref(1);
const showInfo = ref(false);
const showProductDetailsModal = ref(false);
const selectedProductEan = ref<string | null>(null);
const scale = computed(() => 3 * zoom.value);

const gondolaTitle = computed(() => props.gondola.name || 'Gondola sem nome');
const sectionTitle = computed(() => props.section.name || `Seção ${props.section.ordering ?? ''}`);

const sectionAsArray = computed(() => [props.section]);

const sortedShelves = computed(() => {
    return [...(props.section.shelves || [])].sort((a: any, b: any) => a.ordering - b.ordering);
});

const totalProducts = computed(() => {
    let count = 0;
    for (const shelf of sortedShelves.value) {
        for (const segment of shelf.segments || []) {
            if (segment.layer?.product) count++;
        }
    }
    return count;
});

const totalShelves = computed(() => sortedShelves.value.length);

const zoomIn = () => {
    zoom.value = Math.min(zoom.value + 0.1, 2);
};

const zoomOut = () => {
    zoom.value = Math.max(zoom.value - 0.1, 0.5);
};

const resetZoom = () => {
    zoom.value = 1;
};

const handleProductClick = (ean: string) => {
    selectedProductEan.value = ean;
    showProductDetailsModal.value = true;
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <div class="fixed inset-0 flex flex-col bg-background overflow-hidden">
        <Head :title="`${sectionTitle} - ${gondolaTitle}`" />

        <!-- Header -->
        <div class="shrink-0 border-b bg-card z-10">
            <div class="px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Button variant="ghost" size="icon" class="size-8" @click="goBack">
                            <ArrowLeft class="size-4" />
                        </Button>
                        <div>
                            <h1 class="text-xl font-bold text-foreground">
                                {{ sectionTitle }}
                            </h1>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                {{ gondolaTitle }}
                            </p>
                        </div>
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

                    <Button variant="outline" size="sm" @click="showInfo = !showInfo">
                        <ActionIconBox variant="outline">
                            <Info />
                        </ActionIconBox>
                        {{ showInfo ? 'Ocultar' : 'Mostrar' }} Info
                    </Button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 relative overflow-hidden">
            <div class="absolute inset-0 overflow-auto bg-muted/20 p-8">
                <div
                    v-if="sortedShelves.length > 0"
                    class="flex min-h-full items-end pt-12 pb-4"
                >
                    <Sections
                        :sections="sectionAsArray"
                        :scale="scale"
                        @product-click="handleProductClick"
                    />
                </div>
                <div v-else class="flex h-full items-center justify-center text-center text-muted-foreground">
                    <div>
                        <Info class="size-12 mx-auto mb-4 opacity-50" />
                        <p>Nenhuma prateleira encontrada nesta seção</p>
                    </div>
                </div>
            </div>

            <!-- Info Sidebar -->
            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-300 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div
                    v-if="showInfo"
                    class="fixed top-0 right-0 h-full w-80 bg-background border-l border-border shadow-xl z-50 flex flex-col"
                >
                    <div class="shrink-0 flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-lg font-semibold">Informações</h2>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            @click="showInfo = false"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4">
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm font-medium text-muted-foreground">Seção</div>
                                <div class="text-lg font-bold">{{ sectionTitle }}</div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-muted-foreground">Gôndola</div>
                                <div class="font-medium">{{ gondolaTitle }}</div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-muted-foreground">Total de Prateleiras</div>
                                <div class="text-2xl font-bold">{{ totalShelves }}</div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-muted-foreground">Total de Produtos</div>
                                <div class="text-2xl font-bold">{{ totalProducts }}</div>
                            </div>

                            <div class="pt-4 border-t">
                                <div class="text-sm font-medium text-muted-foreground mb-2">Dimensões da Seção</div>
                                <div class="text-sm space-y-1">
                                    <div v-if="section.width" class="flex justify-between">
                                        <span class="text-muted-foreground">Largura:</span>
                                        <span class="font-medium">{{ section.width }} cm</span>
                                    </div>
                                    <div v-if="section.height" class="flex justify-between">
                                        <span class="text-muted-foreground">Altura:</span>
                                        <span class="font-medium">{{ section.height }} cm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Product Details Modal -->
        <ProductDetailsModal
            v-model:open="showProductDetailsModal"
            :product-ean="selectedProductEan"
            :gondola-id="gondola.id"
        />
    </div>
</template>
