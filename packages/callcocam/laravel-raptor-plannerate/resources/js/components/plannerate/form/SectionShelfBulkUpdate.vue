<script setup lang="ts">
import {
    BoxIcon,
    GripVerticalIcon,
    LayoutGridIcon,
    RotateCcwIcon,
    RulerIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import {
    calculateUsableHeight,
    DEFAULT_SECTION_FIELDS,
    toCamelCase as sectionToCamelCase,
    toSnakeCase as sectionToSnakeCase,
} from '@/composables/plannerate/useSectionFields';
import {
    calculateShelfSpacing,
    calculateTotalDisplayArea,
    DEFAULT_SHELF_FIELDS,
    toCamelCase as shelfToCamelCase,
    toSnakeCase as shelfToSnakeCase,
} from '@/composables/plannerate/useShelfFields';

const editor = usePlanogramEditor();

// Form data for section properties (inicializado com valores padrão do composable)
const sectionForm = ref({ ...DEFAULT_SECTION_FIELDS });

// Form data for shelf properties (inicializado com valores padrão do composable)
const shelfForm = ref({
    height: DEFAULT_SHELF_FIELDS.shelfHeight,
    width: DEFAULT_SHELF_FIELDS.shelfWidth,
    depth: DEFAULT_SHELF_FIELDS.shelfDepth,
    productType: DEFAULT_SHELF_FIELDS.productType,
});

// Apply to all checkboxes
const applyToAllSections = ref(false);
const applyToAllShelves = ref(false);

// Initialize form with current values from first section/shelf usando composables
const initializeForms = () => {
    const gondola = editor.currentGondola.value;

    if (!gondola?.sections || gondola.sections.length === 0) {
return;
}

    const firstSection = gondola.sections[0];

    // Initialize section form usando composable
    const sectionCamel = sectionToCamelCase(firstSection);
    sectionForm.value = {
        ...DEFAULT_SECTION_FIELDS,
        ...sectionCamel,
    };

    // Initialize shelf form from first shelf of first section usando composable
    if (firstSection.shelves && firstSection.shelves.length > 0) {
        const firstShelf = firstSection.shelves[0];
        const shelfCamel = shelfToCamelCase(firstShelf);
        shelfForm.value = {
            height:
                shelfCamel.shelfHeight ??
                shelfCamel.height ??
                DEFAULT_SHELF_FIELDS.shelfHeight,
            width:
                shelfCamel.shelfWidth ??
                shelfCamel.width ??
                DEFAULT_SHELF_FIELDS.shelfWidth,
            depth:
                shelfCamel.shelfDepth ??
                shelfCamel.depth ??
                DEFAULT_SHELF_FIELDS.shelfDepth,
            productType:
                shelfCamel.productType ?? DEFAULT_SHELF_FIELDS.productType,
        };
    } else {
        // Use defaults if no shelves
        shelfForm.value = {
            height: DEFAULT_SHELF_FIELDS.shelfHeight,
            width: DEFAULT_SHELF_FIELDS.shelfWidth,
            depth: DEFAULT_SHELF_FIELDS.shelfDepth,
            productType: DEFAULT_SHELF_FIELDS.productType,
        };
    }
};

// Initialize on component mount
initializeForms();

// Reset section form to defaults usando composable
const resetSectionToDefaults = () => {
    sectionForm.value = { ...DEFAULT_SECTION_FIELDS };
};

// Reset shelf form to defaults usando composable
const resetShelfToDefaults = () => {
    shelfForm.value = {
        height: DEFAULT_SHELF_FIELDS.shelfHeight,
        width: DEFAULT_SHELF_FIELDS.shelfWidth,
        depth: DEFAULT_SHELF_FIELDS.shelfDepth,
        productType: DEFAULT_SHELF_FIELDS.productType,
    };
};

// Apply section updates usando composable para conversão
const applySectionUpdates = () => {
    const gondola = editor.currentGondola.value;

    if (!gondola?.sections) {
return;
}

    // Converte de camelCase para snake_case usando composable
    const updates = sectionToSnakeCase(sectionForm.value);

    if (applyToAllSections.value) {
        // Apply to all sections
        gondola.sections.forEach((section) => {
            editor.updateSection(section.id, updates);
        });
    } else {
        // Apply only to first section (or selected if we add selection later)
        if (gondola.sections.length > 0) {
            editor.updateSection(gondola.sections[0].id, updates);
        }
    }
};

// Apply shelf updates usando composable para conversão
const applyShelfUpdates = () => {
    const gondola = editor.currentGondola.value;

    if (!gondola?.sections) {
return;
}

    // Converte de camelCase para snake_case usando composable
    const shelfFieldsCamel = {
        shelfHeight: shelfForm.value.height,
        shelfWidth: shelfForm.value.width,
        shelfDepth: shelfForm.value.depth,
        productType: shelfForm.value.productType,
    };
    const updates = shelfToSnakeCase(shelfFieldsCamel);

    if (applyToAllShelves.value) {
        // Apply to all shelves in all sections
        gondola.sections.forEach((section) => {
            section.shelves?.forEach((shelf) => {
                editor.updateShelf(shelf.id, updates);
            });
        });
    } else {
        // Apply only to shelves in first section
        if (gondola.sections.length > 0 && gondola.sections[0].shelves) {
            gondola.sections[0].shelves.forEach((shelf) => {
                editor.updateShelf(shelf.id, updates);
            });
        }
    }
};

// Computed values for section calculations usando composables
const usableHeight = computed(() => {
    return calculateUsableHeight(
        sectionForm.value.height,
        sectionForm.value.baseHeight,
    );
});

const averageShelfSpacing = computed(() => {
    const gondola = editor.currentGondola.value;

    if (!gondola?.sections || gondola.sections.length === 0) {
return 0;
}

    const firstSection = gondola.sections[0];
    const numShelves = firstSection.shelves?.length ?? 0;

    if (numShelves === 0) {
return 0;
}

    const spacing = calculateShelfSpacing(
        usableHeight.value,
        shelfForm.value.height,
        numShelves,
    );

    return spacing.toFixed(1);
});

const totalDisplayArea = computed(() => {
    const gondola = editor.currentGondola.value;

    if (!gondola?.sections || gondola.sections.length === 0) {
return 0;
}

    let totalShelves = 0;
    gondola.sections.forEach((section) => {
        totalShelves += section.shelves?.length ?? 0;
    });

    const area = calculateTotalDisplayArea(
        shelfForm.value.width,
        shelfForm.value.depth,
        totalShelves,
        gondola.sections.length,
    );

    return area.toFixed(0);
});

// Count total sections and shelves
const totalSections = computed(() => {
    return editor.currentGondola.value?.sections?.length ?? 0;
});

const totalShelves = computed(() => {
    const gondola = editor.currentGondola.value;

    if (!gondola?.sections) {
return 0;
}

    let count = 0;
    gondola.sections.forEach((section) => {
        count += section.shelves?.length ?? 0;
    });

    return count;
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium">Atualização em Massa</h3>
                <p class="text-sm text-muted-foreground">
                    Edite propriedades de módulos e prateleiras
                </p>
            </div>
        </div>

        <Accordion type="single" collapsible class="w-full">
            <!-- Section Properties Accordion -->
            <AccordionItem value="sections">
                <AccordionTrigger>
                    <div class="flex items-center gap-2">
                        <LayoutGridIcon class="h-4 w-4" />
                        <span>Propriedades dos Módulos</span>
                        <span class="text-xs text-muted-foreground"
                            >({{ totalSections }})</span
                        >
                    </div>
                </AccordionTrigger>
                <AccordionContent class="space-y-4 px-1">
                    <!-- Module Dimensions -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <LayoutGridIcon class="h-4 w-4" />
                            <Label class="text-sm font-medium"
                                >Dimensões do Módulo</Label
                            >
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="section-height" class="text-xs"
                                    >Altura (cm)</Label
                                >
                                <Input
                                    id="section-height"
                                    type="number"
                                    v-model.number="sectionForm.height"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="section-width" class="text-xs"
                                    >Largura (cm)</Label
                                >
                                <Input
                                    id="section-width"
                                    type="number"
                                    v-model.number="sectionForm.width"
                                    min="1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Base Configuration -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <BoxIcon class="h-4 w-4" />
                            <Label class="text-sm font-medium">Base</Label>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="space-y-1.5">
                                <Label for="base-height" class="text-xs"
                                    >Altura</Label
                                >
                                <Input
                                    id="base-height"
                                    type="number"
                                    v-model.number="sectionForm.baseHeight"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="base-width" class="text-xs"
                                    >Largura</Label
                                >
                                <Input
                                    id="base-width"
                                    type="number"
                                    v-model.number="sectionForm.baseWidth"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="base-depth" class="text-xs"
                                    >Profund.</Label
                                >
                                <Input
                                    id="base-depth"
                                    type="number"
                                    v-model.number="sectionForm.baseDepth"
                                    min="1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Rack Configuration -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <GripVerticalIcon class="h-4 w-4" />
                            <Label class="text-sm font-medium"
                                >Cremalheira</Label
                            >
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1.5">
                                <Label for="rack-width" class="text-xs"
                                    >Largura</Label
                                >
                                <Input
                                    id="rack-width"
                                    type="number"
                                    v-model.number="sectionForm.rackWidth"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="hole-spacing" class="text-xs"
                                    >Espaçamento</Label
                                >
                                <Input
                                    id="hole-spacing"
                                    type="number"
                                    v-model.number="sectionForm.holeSpacing"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="hole-height" class="text-xs"
                                    >Alt. Furo</Label
                                >
                                <Input
                                    id="hole-height"
                                    type="number"
                                    v-model.number="sectionForm.holeHeight"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="hole-width" class="text-xs"
                                    >Larg. Furo</Label
                                >
                                <Input
                                    id="hole-width"
                                    type="number"
                                    v-model.number="sectionForm.holeWidth"
                                    min="1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Apply to All Checkbox -->
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="apply-all-sections"
                            :model-value="applyToAllSections"
                            @update:model-value="
                                (val) => {
                                    applyToAllSections = Boolean(val);
                                }
                            "
                        />
                        <Label
                            for="apply-all-sections"
                            class="text-sm font-normal"
                        >
                            Aplicar para todos os {{ totalSections }} módulos
                        </Label>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            @click="applySectionUpdates"
                            class="flex-1"
                        >
                            Aplicar
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="resetSectionToDefaults"
                        >
                            <RotateCcwIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </AccordionContent>
            </AccordionItem>

            <!-- Shelf Properties Accordion -->
            <AccordionItem value="shelves">
                <AccordionTrigger>
                    <div class="flex items-center gap-2">
                        <RulerIcon class="h-4 w-4" />
                        <span>Propriedades das Prateleiras</span>
                        <span class="text-xs text-muted-foreground"
                            >({{ totalShelves }})</span
                        >
                    </div>
                </AccordionTrigger>
                <AccordionContent class="space-y-4 px-1">
                    <!-- Shelf Dimensions -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <RulerIcon class="h-4 w-4" />
                            <Label class="text-sm font-medium"
                                >Dimensões da Prateleira</Label
                            >
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="space-y-1.5">
                                <Label for="shelf-height" class="text-xs"
                                    >Espessura</Label
                                >
                                <Input
                                    id="shelf-height"
                                    type="number"
                                    v-model.number="shelfForm.height"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="shelf-width" class="text-xs"
                                    >Largura</Label
                                >
                                <Input
                                    id="shelf-width"
                                    type="number"
                                    v-model.number="shelfForm.width"
                                    min="1"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="shelf-depth" class="text-xs"
                                    >Profund.</Label
                                >
                                <Input
                                    id="shelf-depth"
                                    type="number"
                                    v-model.number="shelfForm.depth"
                                    min="1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Product Type -->
                    <div class="space-y-2">
                        <Label class="text-sm">Tipo de Produto</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                :variant="
                                    shelfForm.productType === 'normal'
                                        ? 'default'
                                        : 'outline'
                                "
                                @click="shelfForm.productType = 'normal'"
                                type="button"
                                size="sm"
                            >
                                Normal
                            </Button>
                            <Button
                                :variant="
                                    shelfForm.productType === 'hook'
                                        ? 'default'
                                        : 'outline'
                                "
                                @click="shelfForm.productType = 'hook'"
                                type="button"
                                size="sm"
                            >
                                Gancheira
                            </Button>
                        </div>
                    </div>

                    <!-- Calculations -->
                    <div
                        class="space-y-2 rounded-lg border bg-muted/50 p-3 text-xs"
                    >
                        <h4 class="font-medium">Cálculos</h4>
                        <div class="space-y-1">
                            <div class="flex justify-between">
                                <span>Altura útil:</span>
                                <span>{{ usableHeight }} cm</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Espaçamento médio:</span>
                                <span>{{ averageShelfSpacing }} cm</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Área total:</span>
                                <span>{{ totalDisplayArea }} cm²</span>
                            </div>
                        </div>
                    </div>

                    <!-- Apply to All Checkbox -->
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="apply-all-shelves"
                            :model-value="applyToAllShelves"
                            @update:model-value="
                                (val) => {
                                    applyToAllShelves = Boolean(val);
                                }
                            "
                        />
                        <Label
                            for="apply-all-shelves"
                            class="text-sm font-normal"
                        >
                            Aplicar para todas as {{ totalShelves }} prateleiras
                        </Label>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            @click="applyShelfUpdates"
                            class="flex-1"
                        >
                            Aplicar
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="resetShelfToDefaults"
                        >
                            <RotateCcwIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    </div>
</template>
