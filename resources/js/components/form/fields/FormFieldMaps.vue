<!--
 * FormFieldMaps - Store map editor with region mapping
 *
 * Features:
 * - Upload store floor plan image
 * - Draw rectangles to mark gondola/island positions
 * - Associate regions with gondolas
 * - Inline regions list sidebar
 * - Zoom and pan controls
 -->
<template>
    <Field orientation="vertical" :data-invalid="hasError" class="gap-y-1">
        <div class="flex w-full items-center justify-between gap-2">
            <FieldLabel v-if="column.label" :for="column.name">
                {{ column.label }}
                <span v-if="column.required" class="text-destructive">*</span>
            </FieldLabel>

            <Button
                v-if="mapImage && !showExpandedEditor"
                type="button"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="openExpandedEditor"
            >
                <Maximize2 class="mr-2 h-4 w-4" />
                Editor ampliado
            </Button>
        </div>

        <!-- Upload area when no map -->
        <MapUploadArea v-if="!mapImage" @upload="triggerFileUpload" />

        <!-- Map editor (inline) -->
        <div
            v-else-if="!showExpandedEditor"
            class="relative overflow-hidden rounded-lg border bg-muted/30"
        >
            <div class="flex flex-col lg:flex-row">
                <div class="relative flex-1">
                    <MapToolbar
                        :zoom="zoom"
                        :current-tool="currentTool"
                        :draw-shape="drawShape"
                        @zoom-in="zoomIn"
                        @zoom-out="zoomOut"
                        @tool-change="currentTool = $event"
                        @shape-change="drawShape = $event"
                        @reset-view="resetView"
                        @change-image="triggerFileUpload"
                    />
                    <MapCanvas
                        ref="mapCanvasRef"
                        :map-image="mapImage || ''"
                        :regions="regions"
                        :selected-region-id="selectedRegion?.id || null"
                        :current-tool="currentTool"
                        :draw-shape="drawShape"
                        :zoom="zoom"
                        :pan-x="panX"
                        :pan-y="panY"
                        :container-height="containerHeight"
                        @select-region="selectRegion"
                        @edit-region="editRegion"
                        @draw-complete="handleDrawComplete"
                        @region-move="handleRegionMove"
                        @region-resize="handleRegionResize"
                        @update:zoom="zoom = $event"
                        @update:pan-x="panX = $event"
                        @update:pan-y="panY = $event"
                        @image-loaded="handleImageLoaded"
                        @deselect="selectedRegion = null"
                    />
                </div>

                <MapRegionsList
                    :regions="regions"
                    :selected-region-id="selectedRegion?.id || null"
                    :max-height="containerHeight - 92"
                    @select="selectRegion"
                    @edit="editRegion"
                    @duplicate="duplicateRegion"
                />
            </div>
        </div>

        <!-- Expanded map editor -->
        <Dialog
            :open="showExpandedEditor"
            @update:open="showExpandedEditor = $event"
        >
            <DialogContent
                :show-close="false"
                class="h-[92vh] w-[98vw] max-w-[98vw] p-0 sm:max-w-[98vw]"
            >
                <div class="flex h-full min-h-0 flex-col">
                    <div
                        class="flex items-center justify-between border-b px-4 py-3"
                    >
                        <div>
                            <h3 class="text-base font-semibold">
                                Editor ampliado do mapa
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Mais espaço para desenhar e ajustar as regiões
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showExpandedEditor = false"
                        >
                            Fechar
                        </Button>
                    </div>

                    <div class="min-h-0 flex-1 p-4">
                        <div
                            class="relative h-full overflow-hidden rounded-lg border bg-muted/30"
                        >
                            <div
                                class="flex h-full min-h-0 flex-col xl:flex-row"
                            >
                                <div class="relative min-h-0 flex-1">
                                    <MapToolbar
                                        :zoom="zoom"
                                        :current-tool="currentTool"
                                        :draw-shape="drawShape"
                                        @zoom-in="zoomIn"
                                        @zoom-out="zoomOut"
                                        @tool-change="currentTool = $event"
                                        @shape-change="drawShape = $event"
                                        @reset-view="resetView"
                                        @change-image="triggerFileUpload"
                                    />
                                    <MapCanvas
                                        ref="mapCanvasRef"
                                        :map-image="mapImage || ''"
                                        :regions="regions"
                                        :selected-region-id="
                                            selectedRegion?.id || null
                                        "
                                        :current-tool="currentTool"
                                        :draw-shape="drawShape"
                                        :zoom="zoom"
                                        :pan-x="panX"
                                        :pan-y="panY"
                                        :container-height="
                                            expandedContainerHeight
                                        "
                                        @select-region="selectRegion"
                                        @edit-region="editRegion"
                                        @draw-complete="handleDrawComplete"
                                        @region-move="handleRegionMove"
                                        @region-resize="handleRegionResize"
                                        @update:zoom="zoom = $event"
                                        @update:pan-x="panX = $event"
                                        @update:pan-y="panY = $event"
                                        @image-loaded="handleImageLoaded"
                                        @deselect="selectedRegion = null"
                                    />
                                </div>

                                <MapRegionsList
                                    :regions="regions"
                                    :selected-region-id="
                                        selectedRegion?.id || null
                                    "
                                    :max-height="expandedContainerHeight - 92"
                                    @select="selectRegion"
                                    @edit="editRegion"
                                    @duplicate="duplicateRegion"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <FieldDescription v-if="column.helpText">
            {{ column.helpText }}
        </FieldDescription>

        <FieldError :errors="errorArray" />

        <!-- Region edit dialog -->
        <MapRegionDialog
            :open="showRegionDialog"
            :is-editing="!!editingRegion"
            :initial-form="regionForm"
            :gondolas="availableGondolas"
            :regions="regions"
            @update:open="showRegionDialog = $event"
            @save="saveRegion"
            @delete="deleteRegion"
            @duplicate="duplicateEditingRegion"
            @close="closeRegionDialog"
        />

        <!-- Hidden file input -->
        <input
            ref="fileInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleFileUpload"
        />
    </Field>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Maximize2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    MapCanvas,
    MapRegionDialog,
    MapRegionsList,
    MapToolbar,
    MapUploadArea,
} from './partials/maps';

// Helper function to generate ULID (26 character, compatible with Laravel ULIDs)
function generateULID(): string {
    const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford's Base32
    const ENCODING_LEN = ENCODING.length;
    const TIME_LEN = 10;
    const RANDOM_LEN = 16;

    let now = Date.now();
    let timeChars = '';
    for (let i = TIME_LEN - 1; i >= 0; i--) {
        timeChars = ENCODING[now % ENCODING_LEN] + timeChars;
        now = Math.floor(now / ENCODING_LEN);
    }

    let randomChars = '';
    for (let i = 0; i < RANDOM_LEN; i++) {
        randomChars += ENCODING[Math.floor(Math.random() * ENCODING_LEN)];
    }

    return (timeChars + randomChars).toLowerCase();
}

interface Region {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    shape?: 'rectangle' | 'circle';
    label?: string;
    type?: string;
    color?: string;
    gondola_id?: string | null;
    gondola?: { id: string; name: string } | null;
}

interface Gondola {
    id: string;
    name: string;
}

interface FormColumn {
    name: string;
    label?: string;
    required?: boolean;
    disabled?: boolean;
    helpText?: string;
    gondolas?: Gondola[];
}

interface MapData {
    image?: string;
    image_url?: string;
    regions: Region[];
}

interface RegionForm {
    label: string;
    type: string;
    color: string;
    gondola_id: string | null;
    width: number;
    height: number;
}

interface Props {
    column: FormColumn;
    modelValue?: MapData | null;
    error?: string | string[];
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    error: undefined,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: MapData | null): void;
}>();

// Refs
const fileInput = ref<HTMLInputElement | null>(null);
const mapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);

// State
const mapImage = ref<string | null>(
    props.modelValue?.image_url || props.modelValue?.image || null,
);
const isNewImage = ref(false);
const regions = ref<Region[]>(props.modelValue?.regions || []);
const containerHeight = ref(400);
const expandedContainerHeight = ref(620);

// Tools and interaction
const currentTool = ref<'select' | 'draw' | 'pan'>('select');
const drawShape = ref<'rectangle' | 'circle'>('rectangle');
const selectedRegion = ref<Region | null>(null);

// View state
const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);

// Dialogs
const showRegionDialog = ref(false);
const showExpandedEditor = ref(false);
const editingRegion = ref<Region | null>(null);
const newRegionPending = ref<Region | null>(null);
const regionForm = ref<RegionForm>({
    label: '',
    type: 'gondola',
    color: 'rgba(59, 130, 246, 0.3)',
    gondola_id: null,
    width: 120,
    height: 80,
});

// Gera label sugerido baseado no tipo
const generateSuggestedLabel = (type: string): string => {
    const prefixes: Record<string, string> = {
        gondola: 'G',
        island: 'I',
        checkout: 'CK',
        entrance: 'E',
        exit: 'S',
        storage: 'EST',
        other: 'A',
    };
    const prefix = prefixes[type] || 'A';
    const countOfType = regions.value.filter((r) => r.type === type).length;
    const nextNumber = String(countOfType + 1).padStart(2, '0');
    return `${prefix}-${nextNumber}`;
};

// Computed
const hasError = computed(() => !!props.error);

const errorArray = computed(() => {
    if (!props.error) return [];
    if (Array.isArray(props.error)) {
        return props.error.map((msg) => ({ message: msg }));
    }
    return [{ message: props.error }];
});

const availableGondolas = computed(() => props.column.gondolas || []);
const MIN_REGION_SIZE = 20;

const normalizeDimension = (value: number, fallback: number): number => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
        return Math.max(MIN_REGION_SIZE, Math.round(fallback));
    }

    return Math.max(MIN_REGION_SIZE, Math.round(parsed));
};

const generateDuplicatedLabel = (sourceLabel?: string): string => {
    const baseLabel = (sourceLabel?.trim() || 'Área')
        .replace(/\s+\(cópia(?:\s+\d+)?\)$/i, '')
        .trim();

    const existingLabels = new Set(
        regions.value
            .map((region) => region.label?.trim().toLowerCase())
            .filter((label): label is string => !!label),
    );

    let nextLabel = `${baseLabel} (cópia)`;
    let copyNumber = 2;

    while (existingLabels.has(nextLabel.toLowerCase())) {
        nextLabel = `${baseLabel} (cópia ${copyNumber})`;
        copyNumber += 1;
    }

    return nextLabel;
};

// Methods
const triggerFileUpload = () => {
    fileInput.value?.click();
};

const openExpandedEditor = () => {
    showExpandedEditor.value = true;
};

const handleFileUpload = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        mapImage.value = e.target?.result as string;
        isNewImage.value = true;
        resetView();
        emitUpdate();
    };
    reader.readAsDataURL(file);
};

const handleImageLoaded = () => {
    mapCanvasRef.value?.fitToContainer();
};

const zoomIn = () => {
    zoom.value = Math.min(3, zoom.value + 0.1);
};

const zoomOut = () => {
    zoom.value = Math.max(0.1, zoom.value - 0.1);
};

const resetView = () => {
    zoom.value = 1;
    panX.value = 0;
    panY.value = 0;
};

const selectRegion = (region: Region) => {
    selectedRegion.value = region;
    currentTool.value = 'select';
};

const editRegion = (region: Region) => {
    editingRegion.value = region;
    newRegionPending.value = null;
    regionForm.value = {
        label: region.label || '',
        type: region.type || 'gondola',
        color: region.color || 'rgba(59, 130, 246, 0.3)',
        gondola_id: region.gondola_id || null,
        width: region.width,
        height: region.height,
    };
    showRegionDialog.value = true;
};

const handleDrawComplete = (regionData: Omit<Region, 'id'>) => {
    const newRegion: Region = {
        id: generateULID(),
        ...regionData,
    };

    newRegionPending.value = newRegion;
    editingRegion.value = null;
    const suggestedLabel = generateSuggestedLabel('gondola');
    regionForm.value = {
        label: suggestedLabel,
        type: 'gondola',
        color: 'rgba(59, 130, 246, 0.3)',
        gondola_id: null,
        width: regionData.width,
        height: regionData.height,
    };
    showRegionDialog.value = true;
};

const handleRegionMove = (regionId: string, x: number, y: number) => {
    const region = regions.value.find((r) => r.id === regionId);
    if (region) {
        region.x = x;
        region.y = y;
    }
};

const handleRegionResize = (
    regionId: string,
    x: number,
    y: number,
    width: number,
    height: number,
) => {
    const region = regions.value.find((r) => r.id === regionId);
    if (region) {
        region.x = x;
        region.y = y;
        region.width = width;
        region.height = height;
        emitUpdate();
    }
};

const closeRegionDialog = () => {
    showRegionDialog.value = false;
    newRegionPending.value = null;
    editingRegion.value = null;
};

const duplicateRegion = (region: Region) => {
    const duplicatedRegion: Region = {
        ...region,
        id: generateULID(),
        x: Math.round(region.x + 20),
        y: Math.round(region.y + 20),
        width: normalizeDimension(region.width, region.width),
        height: normalizeDimension(region.height, region.height),
        label: generateDuplicatedLabel(region.label),
        gondola_id: null,
        gondola: null,
    };

    regions.value.push(duplicatedRegion);
    selectedRegion.value = duplicatedRegion;
    emitUpdate();
};

const duplicateEditingRegion = () => {
    if (!editingRegion.value) return;
    const regionToDuplicate = editingRegion.value;
    closeRegionDialog();
    duplicateRegion(regionToDuplicate);
};

const saveRegion = (form: RegionForm) => {
    const normalizedWidth = normalizeDimension(
        form.width,
        editingRegion.value?.width ??
            newRegionPending.value?.width ??
            MIN_REGION_SIZE,
    );
    const normalizedHeight = normalizeDimension(
        form.height,
        editingRegion.value?.height ??
            newRegionPending.value?.height ??
            MIN_REGION_SIZE,
    );

    if (newRegionPending.value) {
        newRegionPending.value.label = form.label;
        newRegionPending.value.type = form.type;
        newRegionPending.value.color = form.color;
        newRegionPending.value.gondola_id = form.gondola_id;
        newRegionPending.value.gondola = form.gondola_id
            ? availableGondolas.value.find((g) => g.id === form.gondola_id) ||
              null
            : null;
        newRegionPending.value.width = normalizedWidth;
        newRegionPending.value.height = normalizedHeight;

        regions.value.push(newRegionPending.value);
        selectedRegion.value = newRegionPending.value;
    } else if (editingRegion.value) {
        editingRegion.value.label = form.label;
        editingRegion.value.type = form.type;
        editingRegion.value.color = form.color;
        editingRegion.value.gondola_id = form.gondola_id;
        editingRegion.value.gondola = form.gondola_id
            ? availableGondolas.value.find((g) => g.id === form.gondola_id) ||
              null
            : null;
        editingRegion.value.width = normalizedWidth;
        editingRegion.value.height = normalizedHeight;
    }

    showRegionDialog.value = false;
    newRegionPending.value = null;
    editingRegion.value = null;
    emitUpdate();
};

const deleteRegion = () => {
    if (editingRegion.value) {
        const index = regions.value.findIndex(
            (r) => r.id === editingRegion.value!.id,
        );
        if (index > -1) {
            regions.value.splice(index, 1);
        }
        if (selectedRegion.value?.id === editingRegion.value.id) {
            selectedRegion.value = null;
        }
    }
    showRegionDialog.value = false;
    editingRegion.value = null;
    emitUpdate();
};

const emitUpdate = () => {
    if (!mapImage.value) {
        emit('update:modelValue', null);
        return;
    }

    const data: MapData = {
        regions: JSON.parse(JSON.stringify(regions.value)),
    };

    if (isNewImage.value) {
        data.image = mapImage.value;
    }

    emit('update:modelValue', data);
};

// Watch for external updates
watch(
    () => props.modelValue,
    (newValue) => {
        if (!newValue) return;

        const newRegionsJson = JSON.stringify(newValue.regions || []);
        const currentRegionsJson = JSON.stringify(regions.value);

        if (newRegionsJson === currentRegionsJson) {
            return;
        }

        mapImage.value = newValue.image_url || newValue.image || null;
        regions.value = newValue.regions || [];
        isNewImage.value = false;
    },
    { deep: true },
);

watch(showExpandedEditor, () => {
    nextTick(() => {
        mapCanvasRef.value?.fitToContainer();
    });
});

watch(mapImage, (value) => {
    if (!value) {
        showExpandedEditor.value = false;
    }
});

// Keyboard shortcuts
const handleKeyDown = (event: KeyboardEvent) => {
    if (showRegionDialog.value) return;

    if (event.key === 'Delete' || event.key === 'Backspace') {
        if (selectedRegion.value) {
            editingRegion.value = selectedRegion.value;
            deleteRegion();
        }
    } else if (event.key === 'Escape') {
        selectedRegion.value = null;
        currentTool.value = 'select';
    } else if (event.key === 'd' || event.key === 'D') {
        currentTool.value = 'draw';
    } else if (event.key === 'v' || event.key === 'V') {
        currentTool.value = 'select';
    } else if (event.key === ' ') {
        event.preventDefault();
        currentTool.value = 'pan';
    }
};

const handleKeyUp = (event: KeyboardEvent) => {
    if (event.key === ' ' && currentTool.value === 'pan') {
        currentTool.value = 'select';
    }
};

const updateExpandedContainerHeight = () => {
    expandedContainerHeight.value = Math.max(360, window.innerHeight - 220);
};

onMounted(() => {
    updateExpandedContainerHeight();
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);
    window.addEventListener('resize', updateExpandedContainerHeight);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('keyup', handleKeyUp);
    window.removeEventListener('resize', updateExpandedContainerHeight);
});
</script>
