<template>
    <div
        :style="sectionStyle"
        class="relative transition-all"
        :class="{
            'ring-1 ring-primary ring-offset-1': isSelected,
            'hover:bg-accent/5': !isSelected,
            'bg-primary/5 ring-2 ring-primary': isShelfDropTarget,
        }"
        @click="handleSelectSection"
        @dblclick="handleDoubleClick"
        @dragover.prevent="handleSectionDragOver"
        @dragleave="handleSectionDragLeave"
        @drop.prevent="handleSectionDrop"
    >
        <Cremalheira :section="section" side="left" :scale="scale" />
        <Shelves
            :shelves="sortedShelves"
            :section="section"
            :scale="scale"
            :sectionWidth="sectionWidth"
            :sectionHeight="sectionHeight"
            :holes="holes"
            :cremalheiraWidth="cremalheiraWidth"
        />
        <div
            class="absolute bottom-0 left-0 flex w-full items-center justify-center"
        >
            <div class="text-xs text-muted-foreground">
                Modulo #{{ section.ordering }}
            </div>
        </div>
        <Cremalheira :section="section" side="right" :scale="scale" />
    </div>
</template>

<script setup lang="ts">
import { ulid } from 'ulid';
import { computed, ref } from 'vue';
import {
    draggingShelfId,
    draggingShelfOffset,
    draggingShelfSectionId,
} from '../../../composables/plannerate/editor/useGondolaState';
import { findShelfById } from '../../../composables/plannerate/editor/useLookupHelpers';
import { usePlanogramEditor } from '../../../composables/plannerate/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/usePlanogramSelection';
import {
    DEFAULT_SECTION_FIELDS,
    toCamelCase,
} from '../../../composables/plannerate/useSectionFields';
import {
    calculateHoles,
    findNearestHole,
} from '../../../composables/plannerate/useSectionHoles';
import type { Section as SectionType } from '../../../types/planogram';
import Cremalheira from './Cremalheira.vue';
import Shelves from './Shelves.vue';
interface Props {
    section: SectionType;
    lastSection?: boolean;
    firstSection?: boolean;
    scale: number;
}

const props = defineProps<Props>();

const scale = computed(() => props.scale || 3);

const sectionHeight = computed(() => props.section.height * scale.value);
const cremalheiraWidth = computed(
    () => props.section.cremalheira_width * scale.value,
);
const sectionWidth = computed(() => props.section.width * scale.value);
const totalWidth = computed(
    () => sectionWidth.value + 2 * cremalheiraWidth.value,
);
const selection = usePlanogramSelection();
const editor = usePlanogramEditor();

// ===== Estado de Drag & Drop de Shelves =====
const isShelfDropTarget = ref(false);

function handleSectionDragOver(event: DragEvent) {
    // Só aceita se estiver arrastando uma shelf
    if (!draggingShelfId.value) {
return;
}

    event.preventDefault();
    event.dataTransfer!.dropEffect = 'move';
    isShelfDropTarget.value = true;
}

function handleSectionDragLeave(event: DragEvent) {
    if (!draggingShelfId.value) {
return;
}

    const target = event.currentTarget as HTMLElement;
    const relatedTarget = event.relatedTarget as HTMLElement;

    // Só remove destaque se saiu completamente da section
    if (!target.contains(relatedTarget)) {
        isShelfDropTarget.value = false;
    }
}

function handleSectionDrop(event: DragEvent) {
    if (!draggingShelfId.value || !draggingShelfSectionId.value) {
        console.warn('⚠️ Drop sem shelf sendo arrastada');

        return;
    }

    const draggedShelfId = draggingShelfId.value;
    const sourceSectionId = draggingShelfSectionId.value;

    // Busca a prateleira sendo arrastada para obter sua altura
    const shelfResult = findShelfById(draggedShelfId);

    if (!shelfResult) {
        console.warn('⚠️ Prateleira não encontrada:', draggedShelfId);

        return;
    }

    const draggedShelf = shelfResult.shelf;
    const shelfHeightCm = draggedShelf.shelf_height || 4;

    // Obtém o elemento da section e suas dimensões
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();

    // Posição do mouse em relação ao topo da section (em pixels)
    const mouseY = event.clientY - rect.top;

    // Ajusta a posição considerando onde o usuário clicou na prateleira (offset)
    // O offset é a distância do topo da prateleira até onde foi clicado (em pixels)
    const shelfOffsetPx = draggingShelfOffset.value || 0;

    // Calcula a posição onde o topo da prateleira deve estar (do topo da section)
    // Corrige salto: evita valores negativos/fora da seção por causa do offset
    const shelfTopYRaw = mouseY - shelfOffsetPx;
    const shelfTopY = Math.max(0, Math.min(rect.height, shelfTopYRaw));

    // Converte de pixels para centímetros (usando a escala)
    const shelfTopCm = shelfTopY / scale.value;

    // Obtém dimensões da section para conversão correta
    const sectionCamel = toCamelCase(props.section);
    const baseHeightCm =
        sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;

    // Sistema de coordenadas:
    // - shelf_position = 0 é o TOPO visual da seção
    // - baseHeight é a altura da BASE (rodapé) na parte de BAIXO
    // - Área útil vai de 0 até (height - baseHeight)
    // - Os furos são calculados dentro da área útil (posições relativas de 0 a usableHeight)
    
    // shelfTopCm já é a posição do topo da shelf do topo da section
    // Não precisa subtrair baseHeight pois ele está na parte de baixo!
    
    // Snap ao furo mais próximo
    const nearestHolePosition = findNearestHole(
        props.section,
        shelfTopCm, // Usa diretamente a posição do topo
    );
    
    let finalShelfPosition = nearestHolePosition;

    // Limites: [0, height - baseHeight - shelfHeight]
    const maxShelfTop = (props.section.height ?? 0) - baseHeightCm - shelfHeightCm;
    finalShelfPosition = Math.max(0, Math.min(maxShelfTop, finalShelfPosition));

    // Mesma section - move dentro
    if (sourceSectionId === props.section.id) {
        editor.moveShelfWithinSection(
            draggedShelfId,
            finalShelfPosition,
        );
    } else {
        // Sections diferentes - transfere
        editor.moveShelfToSection(
            draggedShelfId,
            props.section.id,
            finalShelfPosition,
        );
    }

    // Limpa estado
    isShelfDropTarget.value = false;
    draggingShelfId.value = null;
    draggingShelfSectionId.value = null;
    draggingShelfOffset.value = 0;
}

const sectionStyle = computed(() => ({
    width: `${totalWidth.value}px`,
    height: `${sectionHeight.value}px`,
    // Sobrepõe a cremalheira direita da seção anterior (exceto primeira)
    marginLeft: props.firstSection ? '0' : `-${cremalheiraWidth.value}px`,
}));
// Ordena as shelves por shelf_position (de baixo para cima)
const sortedShelves = computed(() => {
    const shelves = [...(props.section.shelves || [])];

    // Filtra prateleiras deletadas e ordena por shelf_position
    return shelves
        .filter((shelf) => !shelf.deleted_at)
        .sort((a, b) => (a.shelf_position || 0) - (b.shelf_position || 0));
});
// Calcula furos usando composable
const holes = computed(() => calculateHoles(props.section));

const isSelected = computed(() => {
    return selection.isSectionSelected(props.section);
});

function handleSelectSection(event: MouseEvent) {
    event.stopPropagation();
    selection.selectItem('section', props.section.id, props.section);
}
// Handler para duplo clique na section (adiciona prateleira)
function handleDoubleClick(event: MouseEvent) {
    if ((event.target as HTMLElement).closest('[data-shelf], [data-segment]')) {
        return;
    }

    const sectionElement = event.currentTarget as HTMLElement;
    const rect = sectionElement.getBoundingClientRect();
    const offsetY = event.clientY - rect.top;
    const yPositionCm = offsetY / props.scale;

    // Obtém dimensões da section para conversão correta
    const sectionCamel = toCamelCase(props.section);
    const baseHeightCm =
        sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;

    // yPositionCm é do topo total, precisa converter para área útil
    const yPositionInUsableArea = yPositionCm - baseHeightCm;

    // Encontra o furo mais próximo da posição clicada (relativo à área útil)
    const nearestHoleInUsableArea = findNearestHole(
        props.section,
        yPositionInUsableArea,
    );

    // Converte de volta para o sistema de coordenadas do topo total
    const nearestHole = baseHeightCm + nearestHoleInUsableArea;

    const shelfHeight =
        sortedShelves.value.length > 0
            ? sortedShelves.value[sortedShelves.value.length - 1].shelf_height
            : 4;
    editor.addShelf(props.section.id, {
        id: ulid(),
        shelf_height: shelfHeight,
        shelf_position: nearestHole, // Usa o furo mais próximo ao invés da posição exata do clique
        section_id: props.section.id,
        product_type: 'normal',
        segments: [],
    });
}
</script>

<style scoped></style>
