<template>
    <div
        :style="sectionStyle"
        class="relative transition-[box-shadow,background-color]"
        :class="{
            'ring-1 ring-primary ring-offset-1': isSelected,
            'hover:bg-accent/5': !isSelected,
            'bg-primary/5 ring-2 ring-primary': isShelfDropTarget,
        }"
        tabindex="0"
        @pointerdown="handlePointerDown"
        @focus="handleFocusSection"
        @click="handleSelectSection"
        @dblclick="handleDoubleClick"
        @contextmenu="handleSectionContextMenu"
        @dragover.prevent="handleSectionDragOver"
        @dragleave="handleSectionDragLeave"
        @drop.prevent="handleSectionDrop"
    >
        <Cremalheira :section="section" side="left" :scale="scale" />
        <!-- Linha de preview: furo onde a prateleira arrastada vai encaixar -->
        <div
            v-if="isShelfDropTarget && shelfDropPreviewCm !== null"
            class="pointer-events-none absolute right-0 left-0 h-[2px] rounded-full bg-primary shadow-[0_0_4px_rgba(0,0,0,0.35)]"
            :style="{ top: `${shelfDropPreviewCm * scale}px`, zIndex: Z.SHELF_DROP_PREVIEW }"
        />
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
            :style="moduleLabelStyle"
        >
            <div class="text-muted-foreground">
                Modulo - {{ section.ordering }}
            </div>
        </div>
        <Cremalheira :section="section" side="right" :scale="scale" />
    </div>
</template>

<script setup lang="ts">
import { ulid } from 'ulid';
import { computed, ref } from 'vue';
import { Z } from '../../../composables/plannerate/constants/zIndex';
import {
    draggingShelfId,
    draggingShelfOffset,
    draggingShelfSectionId,
} from '../../../composables/plannerate/core/useGondolaState';
import { findShelfById } from '../../../composables/plannerate/core/useLookupHelpers';
import { usePlanogramEditor } from '../../../composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/core/usePlanogramSelection';
import { DEFAULT_SHELF_FIELDS } from '../../../composables/plannerate/fields/useShelfFields';
import {
    calculateHoles,
    snapShelfTopToHole,
} from '../../../composables/plannerate/geometry/useSectionHoles';
import { useEditorContextMenu } from '../../../composables/plannerate/interactions/useEditorContextMenu';
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
const { openContextMenu } = useEditorContextMenu();

// ===== Estado de Drag & Drop de Shelves =====
const isShelfDropTarget = ref(false);

/**
 * Furo (cm, do topo da seção) onde a prateleira vai encaixar se for solta
 * agora. Alimenta a linha de preview durante o dragover — antes o usuário só
 * via o realce da seção inteira, sem saber em qual furo cairia.
 */
const shelfDropPreviewCm = ref<number | null>(null);

function handleSectionDragOver(event: DragEvent) {
    // Só aceita se estiver arrastando uma shelf
    if (!draggingShelfId.value) {
return;
}

    event.preventDefault();
    event.dataTransfer!.dropEffect = 'move';
    isShelfDropTarget.value = true;

    // Preview do furo de destino — mesma matemática do drop.
    // Atribui SÓ quando muda (dragover roda a ~60fps; o snap discretiza para
    // poucos valores, então na prática quase nunca dispara reatividade).
    const shelfResult = findShelfById(draggingShelfId.value);

    if (!shelfResult) {
        return;
    }

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const shelfTopY = Math.max(
        0,
        Math.min(
            rect.height,
            event.clientY - rect.top - (draggingShelfOffset.value || 0),
        ),
    );
    const previewCm = snapShelfTopToHole(
        props.section,
        shelfTopY / scale.value,
        shelfResult.shelf.shelf_height || 4,
    );

    if (shelfDropPreviewCm.value !== previewCm) {
        shelfDropPreviewCm.value = previewCm;
    }
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
        shelfDropPreviewCm.value = null;
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

    // Snap ao furo mais próximo + clamp — função canônica compartilhada com o
    // dblclick "adicionar prateleira" (sistema de coordenadas documentado nela)
    const finalShelfPosition = snapShelfTopToHole(
        props.section,
        shelfTopCm,
        shelfHeightCm,
    );

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
    shelfDropPreviewCm.value = null;
    draggingShelfId.value = null;
    draggingShelfSectionId.value = null;
    draggingShelfOffset.value = 0;
}

/**
 * Estilo do rótulo "Modulo #N" no rodapé da seção.
 * O tamanho da fonte acompanha a escala do planograma (mesma proporção
 * das demais medidas), para não ficar desproporcional ao dar zoom in/out.
 */
const moduleLabelStyle = computed(() => ({
    fontSize: `${scale.value * 4}px`,
    lineHeight: '1',
}));

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

// Previne double-fire: ao clicar, o browser dispara @focus ANTES de @click.
// Sem essa flag, selectItem() seria chamado duas vezes no mesmo clique
// (mesmo padrão do Segment.vue). Diferente de lá, a seleção continua no
// @click — a Section não é draggable, então o click nunca é engolido.
let _skipFocusFromMouse = false;

function handlePointerDown(event: PointerEvent) {
    if (event.button !== 0) {
        return;
    }

    _skipFocusFromMouse = true;
    // Reset após o clique completar (focus acontece em < 50ms)
    setTimeout(() => {
        _skipFocusFromMouse = false;
    }, 50);
}

function handleFocusSection() {
    // Ignora focus gerado por mouse — o @click cuida da seleção.
    // Só seleciona em focus por teclado (Tab).
    if (_skipFocusFromMouse) {
        return;
    }

    selection.selectItem('section', props.section.id, props.section);
}

function handleSelectSection(event: MouseEvent) {
    event.stopPropagation();
    selection.selectItem('section', props.section.id, props.section);
}

/**
 * Botão direito na seção (fora de shelves/segmentos, que fazem .stop):
 * seleciona e abre o menu de contexto do editor.
 */
function handleSectionContextMenu(event: MouseEvent) {
    if (event.ctrlKey) {
        // Gesto de Ctrl-drag do macOS — não abre menu (ver Segment.vue)
        event.preventDefault();
        event.stopPropagation();

        return;
    }

    handleSelectSection(event);
    openContextMenu(event, 'section', props.section.id);
}
// Handler para duplo clique na section (adiciona prateleira)
function handleDoubleClick(event: MouseEvent) {
    // [data-shelf-area] incluído: dblclick dentro da área de uma prateleira
    // existente (mesmo em região "vazia" dela) não deve criar outra — só
    // dblclick em espaço realmente livre da seção.
    if ((event.target as HTMLElement).closest('[data-shelf], [data-segment], [data-shelf-area]')) {
        return;
    }

    const sectionElement = event.currentTarget as HTMLElement;
    const rect = sectionElement.getBoundingClientRect();
    const offsetY = event.clientY - rect.top;
    const yPositionCm = offsetY / props.scale;

    const lastShelf =
        sortedShelves.value.length > 0
            ? sortedShelves.value[sortedShelves.value.length - 1]
            : null;

    const newShelfHeight =
        lastShelf?.shelf_height ?? DEFAULT_SHELF_FIELDS.shelfHeight;

    // Snap ao furo mais próximo + clamp — mesma função canônica do drop de
    // prateleira (antes este fluxo deslocava por baseHeight e caía em furo
    // diferente do drop para o mesmo Y)
    const nearestHole = snapShelfTopToHole(
        props.section,
        yPositionCm,
        newShelfHeight,
    );

    const newShelfId = ulid();
    const newShelf = {
        id: newShelfId,
        // Gera código no mesmo padrão do backend: SHELF-{timestamp_segundos}
        code: `SHELF-${Math.floor(Date.now() / 1000)}`,
        shelf_height: newShelfHeight,
        // Herda largura da última prateleira ou usa a largura da seção como padrão
        shelf_width: lastShelf?.shelf_width || props.section.width,
        shelf_depth: lastShelf?.shelf_depth ?? DEFAULT_SHELF_FIELDS.shelfDepth,
        shelf_position: nearestHole, // Usa o furo mais próximo ao invés da posição exata do clique
        section_id: props.section.id,
        product_type: lastShelf?.product_type ?? DEFAULT_SHELF_FIELDS.productType,
        segments: [],
    };

    const created = editor.addShelf(props.section.id, newShelf);

    // Auto-seleciona a prateleira recém-criada para o painel já abrir preenchido
    if (created) {
        selection.selectItem('shelf', created.id, created, {
            section: props.section,
        });
    }
}
</script>

<style scoped></style>
