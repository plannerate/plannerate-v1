<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Edit, Maximize2, Settings, ZoomIn, ZoomOut } from 'lucide-vue-next';

interface MapRegion {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    shape?: 'rectangle' | 'circle';
    type: string;
    color: string;
    label: string;
    gondola_id?: string | null;
    gondola?: {
        id: string;
        name: string;
        slug: string;
        planogram_id: string;
        planogram_name?: string;
        edit_url: string;
    } | null;
}

interface Store {
    id: string;
    name: string;
    code: string;
    can_edit_store?: boolean;
    map_regions?: MapRegion[];
    maps_integration?: {
        image_url?: string;
        regions?: MapRegion[];
    };
}

interface Props {
    open: boolean;
    store: Store | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const hoveredRegion = ref<string | null>(null);
const imageLoaded = ref(false);
const imageWidth = ref(0);
const imageHeight = ref(0);
const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const panStart = ref<{ x: number; y: number } | null>(null);

const gondolasLinked = computed(() => {
    if (!props.store?.map_regions) {
        return [];
    }

    return props.store.map_regions.filter(region => region.gondola);
});

const resetInteraction = (): void => {
    hoveredRegion.value = null;
    imageLoaded.value = false;
    imageWidth.value = 0;
    imageHeight.value = 0;
    zoom.value = 1;
    panX.value = 0;
    panY.value = 0;
    isPanning.value = false;
    panStart.value = null;
};

const handleOpenChange = (open: boolean): void => {
    emit('update:open', open);

    if (!open) {
        resetInteraction();
    }
};

const handleImageLoad = (event: Event): void => {
    const image = event.target as HTMLImageElement;

    imageWidth.value = image.naturalWidth;
    imageHeight.value = image.naturalHeight;
    imageLoaded.value = true;
};

const handleRegionClick = (region: MapRegion): void => {
    if (region.gondola?.edit_url) {
        window.open(region.gondola.edit_url, '_blank', 'noopener,noreferrer');
    }
};

const handleEditStore = (): void => {
    if (!props.store) {
        return;
    }

    window.open(`/stores/${props.store.id}/edit`, '_blank', 'noopener,noreferrer');
};

const zoomIn = (): void => {
    zoom.value = Math.min(zoom.value + 0.2, 3);
};

const zoomOut = (): void => {
    zoom.value = Math.max(zoom.value - 0.2, 0.5);
};

const resetView = (): void => {
    zoom.value = 1;
    panX.value = 0;
    panY.value = 0;
};

const handleMouseDown = (event: MouseEvent): void => {
    const target = event.target as HTMLElement;

    if (
        target.closest('ellipse[class*="cursor-pointer"]') ||
        target.closest('rect[class*="cursor-pointer"]') ||
        target.closest('g.cursor-pointer')
    ) {
        return;
    }

    isPanning.value = true;
    panStart.value = { x: event.clientX - panX.value, y: event.clientY - panY.value };
    event.preventDefault();
};

const handleWheel = (event: WheelEvent): void => {
    if (!event.ctrlKey) {
        return;
    }

    event.preventDefault();
    const delta = event.deltaY > 0 ? -0.1 : 0.1;
    zoom.value = Math.max(0.5, Math.min(3, zoom.value + delta));
};

const globalMouseMove = (event: MouseEvent): void => {
    if (!props.open || !isPanning.value || !panStart.value) {
        return;
    }

    panX.value = event.clientX - panStart.value.x;
    panY.value = event.clientY - panStart.value.y;
};

const globalMouseUp = (): void => {
    isPanning.value = false;
    panStart.value = null;
};

onMounted(() => {
    document.addEventListener('mousemove', globalMouseMove);
    document.addEventListener('mouseup', globalMouseUp);
});

onUnmounted(() => {
    document.removeEventListener('mousemove', globalMouseMove);
    document.removeEventListener('mouseup', globalMouseUp);
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="w-[98vw] max-w-[98vw] h-[92vh] p-0 sm:max-w-[98vw]">
            <div v-if="store" class="h-full flex flex-col">
                <DialogHeader class="border-b px-6 py-4">
                    <DialogTitle>
                        {{ store.name }} (Loja {{ store.code }})
                    </DialogTitle>
                    <DialogDescription>
                        Visualizacao interativa do mapa com zoom e acesso as regioes vinculadas.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex-1 overflow-hidden px-6 py-4 space-y-4">
                    <div class="flex items-center gap-2 p-3 bg-muted/50 rounded-lg">
                        <Button variant="outline" size="sm" @click="zoomOut">
                            <ActionIconBox variant="outline">
                                <ZoomOut />
                            </ActionIconBox>
                        </Button>
                        <span class="text-sm text-muted-foreground min-w-16 text-center font-medium">
                            {{ Math.round(zoom * 100) }}%
                        </span>
                        <Button variant="outline" size="sm" @click="zoomIn">
                            <ActionIconBox variant="outline">
                                <ZoomIn />
                            </ActionIconBox>
                        </Button>
                        <Button variant="outline" size="sm" @click="resetView">
                            <ActionIconBox variant="outline">
                                <Maximize2 />
                            </ActionIconBox>
                        </Button>
                        <span class="text-xs text-muted-foreground ml-auto">
                            Ctrl + roda do mouse para zoom · Arraste para mover o mapa
                        </span>
                    </div>

                    <div class="grid gap-4 h-[calc(92vh-210px)] lg:grid-cols-[1fr_320px]">
                        <div
                            class="relative border rounded-lg overflow-hidden bg-muted select-none"
                            :class="isPanning ? 'cursor-grabbing' : 'cursor-grab'"
                            @mousedown="handleMouseDown"
                            @wheel="handleWheel"
                        >
                            <div
                                class="absolute origin-top-left"
                                :style="{
                                    transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
                                    willChange: isPanning ? 'transform' : 'auto',
                                }"
                            >
                                <img
                                    :src="store.maps_integration?.image_url"
                                    :alt="`Mapa da ${store.name}`"
                                    class="max-w-none select-none"
                                    draggable="false"
                                    @load="handleImageLoad"
                                />

                                <svg
                                    v-if="imageLoaded && store.maps_integration?.regions"
                                    class="absolute top-0 left-0"
                                    :width="imageWidth"
                                    :height="imageHeight"
                                >
                                    <g
                                        v-for="region in store.maps_integration.regions"
                                        :key="region.id"
                                    >
                                        <ellipse
                                            v-if="region.shape === 'circle'"
                                            :cx="region.x + region.width / 2"
                                            :cy="region.y + region.height / 2"
                                            :rx="region.width / 2"
                                            :ry="region.height / 2"
                                            :fill="hoveredRegion === region.id ? 'rgba(59, 130, 246, 0.5)' : region.color"
                                            :stroke="region.gondola ? '#3b82f6' : '#94a3b8'"
                                            :stroke-width="region.gondola ? 3 : 2"
                                            :stroke-dasharray="region.gondola ? '0' : '5,5'"
                                            class="pointer-events-auto"
                                            :class="region.gondola ? 'cursor-pointer' : 'cursor-default'"
                                            @mouseenter="hoveredRegion = region.id"
                                            @mouseleave="hoveredRegion = null"
                                            @click.stop="region.gondola && handleRegionClick(region)"
                                        />

                                        <rect
                                            v-else
                                            :x="region.x"
                                            :y="region.y"
                                            :width="region.width"
                                            :height="region.height"
                                            :fill="hoveredRegion === region.id ? 'rgba(59, 130, 246, 0.5)' : region.color"
                                            :stroke="region.gondola ? '#3b82f6' : '#94a3b8'"
                                            :stroke-width="region.gondola ? 3 : 2"
                                            :stroke-dasharray="region.gondola ? '0' : '5,5'"
                                            class="pointer-events-auto"
                                            :class="region.gondola ? 'cursor-pointer' : 'cursor-default'"
                                            @mouseenter="hoveredRegion = region.id"
                                            @mouseleave="hoveredRegion = null"
                                            @click.stop="region.gondola && handleRegionClick(region)"
                                        />

                                        <text
                                            :x="region.x + region.width / 2"
                                            :y="region.gondola ? region.y + region.height / 2 - 8 : region.y + region.height / 2"
                                            text-anchor="middle"
                                            dominant-baseline="middle"
                                            fill="white"
                                            font-size="14"
                                            font-weight="600"
                                            class="pointer-events-none select-none"
                                            style="text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.9), 0 0 8px rgba(0, 0, 0, 0.6)"
                                        >
                                            {{ region.label }}
                                        </text>

                                        <g
                                            v-if="region.gondola"
                                            class="pointer-events-auto cursor-pointer"
                                            @click.stop="handleRegionClick(region)"
                                            @mouseenter="hoveredRegion = region.id"
                                            @mouseleave="hoveredRegion = null"
                                        >
                                            <circle
                                                :cx="region.x + region.width / 2"
                                                :cy="region.y + region.height / 2 + 14"
                                                r="12"
                                                fill="#3b82f6"
                                                stroke="white"
                                                stroke-width="2"
                                                style="filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3))"
                                            />
                                            <g :transform="`translate(${region.x + region.width / 2 - 5}, ${region.y + region.height / 2 + 9})`">
                                                <path
                                                    d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                                                    fill="none"
                                                    stroke="white"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    transform="scale(0.4)"
                                                />
                                            </g>
                                        </g>

                                        <g v-if="hoveredRegion === region.id && region.gondola" class="pointer-events-none">
                                            <rect
                                                :x="region.x + region.width / 2 - 100"
                                                :y="region.y - 52"
                                                width="200"
                                                height="42"
                                                rx="8"
                                                fill="#1f2937"
                                                opacity="0.96"
                                                style="filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4))"
                                            />
                                            <text
                                                :x="region.x + region.width / 2"
                                                :y="region.y - 36"
                                                text-anchor="middle"
                                                fill="white"
                                                font-size="13"
                                                font-weight="600"
                                            >
                                                {{ region.gondola.name }}
                                            </text>
                                            <text
                                                :x="region.x + region.width / 2"
                                                :y="region.y - 20"
                                                text-anchor="middle"
                                                fill="#d1d5db"
                                                font-size="11"
                                            >
                                                {{ region.gondola.planogram_name?.substring(0, 30) }}{{ region.gondola.planogram_name && region.gondola.planogram_name.length > 30 ? '...' : '' }}
                                            </text>
                                        </g>
                                    </g>
                                </svg>
                            </div>
                        </div>

                        <div class="border rounded-lg p-3 bg-muted/20 flex flex-col min-h-0">
                            <h3 class="text-sm font-medium text-muted-foreground mb-3">
                                Gondolas Vinculadas ({{ gondolasLinked.length }})
                            </h3>

                            <div v-if="gondolasLinked.length > 0" class="space-y-1.5 overflow-y-auto pr-1">
                                <Link
                                    v-for="region in gondolasLinked"
                                    :key="region.id"
                                    :href="region.gondola!.edit_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-accent/50 transition-colors group text-sm"
                                >
                                    <Badge variant="outline" class="shrink-0">
                                        {{ region.label }}
                                    </Badge>
                                    <div class="flex-1 min-w-0">
                                        <span class="font-medium group-hover:text-primary transition-colors">
                                            {{ region.gondola!.name }}
                                        </span>
                                        <span class="text-muted-foreground mx-1.5">.</span>
                                        <span class="text-muted-foreground truncate">
                                            {{ region.gondola!.planogram_name }}
                                        </span>
                                    </div>
                                    <Edit class="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" />
                                </Link>
                            </div>

                            <div v-else class="text-sm text-muted-foreground">
                                Nenhuma gondola vinculada neste mapa.
                            </div>

                            <div v-if="store.can_edit_store" class="mt-auto pt-3 flex justify-end">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="gap-2"
                                    @click="handleEditStore"
                                >
                                    <ActionIconBox variant="outline">
                                        <Settings />
                                    </ActionIconBox>
                                    Editar Loja
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="h-full flex items-center justify-center text-muted-foreground">
                Mapa indisponivel para visualizacao.
            </div>
        </DialogContent>
    </Dialog>
</template>
