<template>
    <!-- Container: Área total da prateleira (do chão/prateleira anterior até esta) -->
    <div
        data-shelf-area="true"
        class="group/shelf absolute"
        :class="[
            isAreaHovered && !isSelected ? 'bg-primary/10' : '',
            isSelected ? 'bg-primary/5 ring-1 ring-inset ring-primary/60' : '',
            showZoneIndicators ? shelfZone.bgClass : '',
            isCategoryHighlighted && !isSelected ? 'ring-4 ring-inset ring-blue-500 bg-blue-500/5' : '',
        ]"
        :style="[shelfAreaStyle, hoverStackStyle]"
        tabindex="0"
        @pointerdown="handleAreaPointerDown"
        @mouseenter="isAreaHovered = true"
        @mouseleave="isAreaHovered = false"
        @focus="handleFocusShelf"
        @click="handleSelectShelf"
        @contextmenu="handleShelfContextMenu"
        @dragover.prevent="handleProductDragOver"
        @dragleave="handleProductDragLeave"
        @drop.prevent="handleProductDrop"
    >
        <!-- Faixa lateral de zona — lateral esquerda -->
        <div
            v-if="showZoneIndicators"
            class="absolute top-0 left-0 flex h-full w-1.5 cursor-default items-center justify-center"
            :class="shelfZone.borderClass"
            :title="shelfZone.label"
            :style="{ zIndex: Z.ZONE }"
            @click.stop
        />
        <!-- Segmentos um do lado do outro (horizontalmente) -->
        <div v-if="segments.length > 0" class="absolute right-0 left-0 flex"
            :class="[alignmentClass, isHookType ? 'items-start' : 'items-end']"
            style="pointer-events: none" :style="[{ zIndex: Z.SEGMENTS }, segmentsPositionStyle, justifyDistributionStyle]">
            <Segment v-for="(segment, index) in segments" :key="segment.id" :segment="segment" :scale="scale"
                :shelf-depth="shelf.shelf_depth" :isFirstInShelf="index === 0"
                :isLastInShelf="index === segments.length - 1" :facing-gap="justifyGap ?? undefined"
                :selected-from-parent="segment.id === selectedSegmentId || multiSelectedSegmentIds.has(segment.id)"
                :layer-selected-from-parent="!!(segment.layer?.id && segment.layer.id === selectedLayerId)"
                :module-number="section.ordering" :shelf-number="shelfDisplayNumber"
                style="pointer-events: auto" />
        </div>

        <!--
            Tampo 3D: superfície da prateleira renderizada ATRÁS dos produtos
            (z-index 40 < segmentos 50), logo acima da base física. Puramente
            decorativo; só aparece no estilo "deck".
        -->
        <div v-if="showDeck" class="shelf-deck pointer-events-none absolute right-0 left-0" :style="{
            top: `${shelfBasePosition - deckDepth}px`,
            height: `${deckDepth}px`,
            zIndex: Z.DECK,
        }" />

        <!-- Drag Handle para mover a shelf -->

        <!-- Área de Drop Personalizada -->
        <Transition enter-active-class="transition-opacity duration-75 ease-out" enter-from-class="opacity-0"
            enter-to-class="opacity-100" leave-active-class="transition-opacity duration-75 ease-in"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="isDropTarget"
                class="pointer-events-none absolute inset-0  flex flex-col items-center justify-center gap-2 rounded-sm border-2 border-dashed border-primary bg-primary/15"
                :style="{
                    zIndex: Z.DROP_OVERLAY,
                }">
                <svg class="size-8 text-primary drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span class="text-sm font-semibold text-primary drop-shadow-lg">
                    {{ t('plannerate.editor.drop_overlay.drop_here') }}
                </span>
                <span class="text-xs font-medium text-primary/80 drop-shadow">
                    {{ t('plannerate.editor.drop_overlay.shelf_label', { number: String(shelfDisplayNumber) }) }}
                </span>
                <!-- Copiar/mover só se aplica a segmento; produto é sempre "copy" -->
                <span v-if="dropKind === 'segment'" class="mt-1 text-[10px] font-light text-primary/70">
                    {{ t('plannerate.editor.drop_overlay.ctrl_to_copy') }}
                </span>
            </div>
        </Transition>

        <!-- Linha de inserção: onde o produto/segmento entra na ordem da shelf -->
        <div v-if="isDropTarget && insertLineX !== null"
            class="pointer-events-none absolute top-0 w-0.5 -translate-x-1/2 rounded-full bg-primary shadow-[0_0_4px_rgba(0,0,0,0.4)]"
            :style="{
                left: `${insertLineX}px`,
                height: `${shelfBasePosition}px`,
                zIndex: Z.DROP_OVERLAY,
            }" />

        <!-- Base da prateleira (superfície física, pseudo-3D) - ARRASTÁVEL -->
        <div data-shelf="true" draggable="true" :style="{
            height: `${shelfHeight}px`,
            top: `${shelfBasePosition}px`,
            zIndex: isDraggingShelf ? Z.SHELF_DRAGGING : Z.SHELF_BASE,
        }"
            class="absolute right-0 left-0 cursor-grab rounded-[2px] active:cursor-grabbing"
            :class="{
                'cursor-grabbing opacity-50 ring-2 ring-primary':
                    isDraggingShelf,
                'hover:ring-1 hover:ring-slate-500':
                    !isDraggingShelf,
                'ring-2 ring-inset ring-blue-500': isCategoryHighlighted,
            }" @mousedown="handleMouseDown" @dragstart.stop="handleShelfDragStart" @dragend.stop="handleShelfDragEnd"
            @click.stop="handleSelectShelf">
            <!-- Superfície física 3D + rótulo "Prat - N" (decoração; não captura eventos) -->
            <ShelfBoard :height="shelfHeight" :scale="scale" :display-number="shelfDisplayNumber"
                :locked="isShelfLocked" />

            <!--
                Travar a prateleira contra a geração automática.
                Travada: cadeado sempre visível (é um estado que muda o que a geração faz, então
                não pode ficar escondido atrás do hover). Destravada: só no hover, para não poluir.
            -->
            <button
                type="button"
                class="absolute top-1/2 right-1 flex size-5 -translate-y-1/2 items-center justify-center rounded transition-opacity"
                :class="isShelfLocked
                    ? 'text-amber-400 opacity-100 hover:text-amber-300'
                    : 'text-slate-400 opacity-0 group-hover/shelf:opacity-100 hover:text-slate-200'"
                :title="isShelfLocked ? t('plannerate.reoptimization.lock.unlock_action') : t('plannerate.reoptimization.lock.lock_action')"
                style="z-index: 2"
                @mousedown.stop
                @dragstart.stop.prevent
                @click.stop="toggleShelfLock"
            >
                <Lock v-if="isShelfLocked" class="size-3.5" />
                <LockOpen v-else class="size-3.5" />
            </button>

            <!-- Indicador visual de arrasto (aparece no hover) -->
        </div>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Lock, LockOpen } from 'lucide-vue-next';
import { computed, ref, toRef, watch } from 'vue';
import { useT } from '@/composables/useT';
import { Z } from '../../../composables/plannerate/constants/zIndex';
import { selectedTemplateCategoryId, shelfBoardStyle, showZoneIndicators } from '../../../composables/plannerate/core/useGondolaState';
import { usePlanogramEditor } from '../../../composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/core/usePlanogramSelection';
import { useShelfLayout } from '../../../composables/plannerate/geometry/useShelfLayout';
import { useEditorContextMenu } from '../../../composables/plannerate/interactions/useEditorContextMenu';
import { useShelfDrag } from '../../../composables/plannerate/interactions/useShelfDrag';
import { useShelfDragDrop } from '../../../composables/plannerate/interactions/useShelfDragDrop';
import type { Section, Shelf as ShelfType } from '../../../types/planogram';
import Segment from './Segment.vue';
import ShelfBoard from './ShelfBoard.vue';

interface Props {
    shelf: ShelfType;
    section: Section;
    scale?: number;
    holes?: any[];
    sectionWidth?: number;
    sectionHeight?: number;
    cremalheiraWidth?: number;
    previousShelf?: ShelfType;
    nextShelf?: ShelfType;
    firstShelf?: ShelfType;
    lastShelf?: ShelfType;
    isLast?: boolean;
    /** Número de exibição ("Prat - N") pré-calculado por Shelves.vue (evita sort O(S) por shelf) */
    displayNumber?: number;
}

const props = defineProps<Props>();

const { t } = useT();

/**
 * Estado do cadeado com atualização otimista.
 *
 * O editor não recarrega a gôndola a cada mutação (`preserveState`), então esperar o servidor
 * responder deixaria o cadeado piscando no estado antigo. Se o PUT falhar, volta.
 */
const isShelfLocked = ref<boolean>(Boolean(props.shelf.is_locked));

watch(() => props.shelf.is_locked, (value) => {
    isShelfLocked.value = Boolean(value);
});

function toggleShelfLock(): void {
    const previous = isShelfLocked.value;
    const next = !previous;

    isShelfLocked.value = next;

    router.put(
        `/api/editor/shelves/${props.shelf.id}/lock`,
        { is_locked: next },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                isShelfLocked.value = previous;
            },
        },
    );
}

/**
 * Empilhamento no hover para resolver prateleiras sobrepostas.
 *
 * Como a área clicável tem altura mínima (ver `useShelfAreaCalculation`), quando
 * duas prateleiras ficam fisicamente próximas suas áreas se sobrepõem. Sem uma
 * ordem definida, passar o mouse sobre a faixa sobreposta fazia o destaque
 * "piscar" alternando entre as duas e dificultava agarrar uma específica.
 *
 * Ao passar o mouse, esta prateleira sobe para a frente (z-index alto) e é a
 * única destacada. Ela permanece na frente enquanto o cursor estiver sobre ela
 * (a caixa não se move, logo não há alternância). Para mexer na outra, basta
 * mover o cursor para a região exclusiva dela.
 */
const isAreaHovered = ref<boolean>(false);

const hoverStackStyle = computed(() =>
    isAreaHovered.value ? { zIndex: Z.SHELF_HOVER } : undefined,
);

const scale = computed(() => props.scale || 3);

const selection = usePlanogramSelection();
const editor = usePlanogramEditor();
const { openContextMenu } = useEditorContextMenu();

const shelfRef = toRef(props, 'shelf');
const sectionRef = toRef(props, 'section');
const previousShelfRef = toRef(props, 'previousShelf');
const sectionWidthRef = toRef(props, 'sectionWidth');
const cremalheiraWidthRef = toRef(props, 'cremalheiraWidth');
const displayNumberRef = toRef(props, 'displayNumber');

// Usa composable para drag & drop de produtos/segments apenas
const {
    isDropTarget,
    dropKind,
    insertLineX,
    handleDragOver: handleProductDragOver,
    handleDragLeave: handleProductDragLeave,
    handleDrop: handleProductDrop,
} = useShelfDragDrop(shelfRef.value.id);

const {
    isDraggingShelf,
    handleMouseDown,
    handleShelfDragStart,
    handleShelfDragEnd,
} = useShelfDrag({
    shelf: shelfRef,
    sectionId: computed(() => sectionRef.value.id),
});

const {
    shelfHeight,
    shelfAreaStyle,
    shelfBasePosition,
    segments,
    isHookType,
    shelfDisplayNumber,
    shelfZone,
    justifyGap,
    alignmentClass,
    justifyDistributionStyle,
    segmentsPositionStyle,
} = useShelfLayout({
    shelf: shelfRef,
    section: sectionRef,
    previousShelf: previousShelfRef,
    scale,
    sectionWidth: sectionWidthRef,
    cremalheiraWidth: cremalheiraWidthRef,
    alignment: computed(() => editor.currentGondola.value?.alignment),
    displayNumber: displayNumberRef,
});

const isSelected = computed(() => selection.isShelfSelected(shelfRef.value));

/**
 * Tampo 3D — superfície da prateleira desenhada ATRÁS dos produtos.
 *
 * Precisa ficar num nível de empilhamento ABAIXO dos segmentos (z-index 50) para
 * os produtos aparecerem apoiados sobre ela; por isso é renderizada aqui (irmã
 * dos segmentos) e não dentro do `<div data-shelf>`, que tem z-index alto (130) e
 * cobriria os produtos. Só existe no estilo `deck`.
 */
const showDeck = computed(() => shelfBoardStyle.value === 'deck');

/** Profundidade visível do tampo em px — acompanha a escala do editor. */
const deckDepth = computed(() => Math.max(6, Math.min(scale.value * 3, 14)));

// ── Seleção de segmentos calculada aqui (nível Shelf), ESCOPADA a esta shelf ──
// Antes, cada Segment subscrevia individualmente a selectedId e selectedItems,
// causando uma cascata de N recomputações por clique (N = nº de segmentos).
// O passo anterior moveu a subscrição para a Shelf, mas os computeds devolviam o
// ID GLOBAL — logo o valor mudava para TODAS as shelves a cada clique, forçando
// todas a re-renderizar o v-for de segmentos (o lag perceptível).
//
// Agora os computeds só devolvem o ID quando o item selecionado pertence a ESTA
// shelf; caso contrário devolvem null/Set vazio ESTÁVEIS. Assim o valor só muda
// nas 2 shelves envolvidas (a que perde e a que ganha a seleção) — todas as
// outras mantêm o mesmo valor e o Vue pula o re-render delas. O comportamento é
// idêntico: o binding nunca compara IDs de outra shelf.

// Set estável reutilizado quando esta shelf não tem nenhum item multi-selecionado.
// Manter a MESMA referência evita que o computed "mude" de valor (Set é comparado
// por identidade) e dispare re-render desnecessário.
const EMPTY_ID_SET: ReadonlySet<string> = new Set();

/** IDs dos segmentos desta shelf — recalcula só quando os segmentos mudam (não na seleção). */
const shelfSegmentIdSet = computed<Set<string>>(
    () => new Set(segments.value.map((s) => s.id)),
);

/** IDs das layers desta shelf — idem, independente da seleção. */
const shelfLayerIdSet = computed<Set<string>>(() => {
    const ids = new Set<string>();

    for (const s of segments.value) {
        if (s.layer?.id) {
            ids.add(s.layer.id);
        }
    }

    return ids;
});

/** ID do segmento em single-select SE pertencer a esta shelf; senão null estável. */
const selectedSegmentId = computed<string | null>(() => {
    if (selection.selectedType.value !== 'segment') {
        return null;
    }

    const id = selection.selectedId.value;

    return id && shelfSegmentIdSet.value.has(id) ? id : null;
});

/** IDs em multi-select restritos a esta shelf (Set para lookup O(1)). */
const multiSelectedSegmentIds = computed<ReadonlySet<string>>(() => {
    const items = selection.selectedItems.value;

    if (!items.length) {
        return EMPTY_ID_SET;
    }

    const own = shelfSegmentIdSet.value;
    let mine: Set<string> | null = null;

    for (const i of items) {
        if (i.type === 'segment' && own.has(i.id)) {
            (mine ??= new Set()).add(i.id);
        }
    }

    return mine ?? EMPTY_ID_SET;
});

/** ID da layer selecionada SE pertencer a esta shelf; senão null estável. */
const selectedLayerId = computed<string | null>(() => {
    if (selection.selectedType.value !== 'layer') {
        return null;
    }

    const id = selection.selectedId.value;

    return id && shelfLayerIdSet.value.has(id) ? id : null;
});
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Verdadeiro quando a categoria do template_slot desta prateleira bate com a
 * categoria selecionada no CategoryConfigPanel.
 * Usado para destaque visual bidirecional (categoria ↔ prateleira).
 *
 * Lê `selectedTemplateCategoryId` direto do estado global (em vez de receber via
 * prop drilada). Só as shelves cujo booleano vira re-renderizam ao trocar a
 * categoria; Canvas/Sections/Section/Shelves não re-renderizam mais em cascata.
 */
const isCategoryHighlighted = computed(
    () =>
        selectedTemplateCategoryId.value != null &&
        !!props.shelf.template_slot?.category_id &&
        props.shelf.template_slot.category_id === selectedTemplateCategoryId.value,
);

// Previne double-fire: ao clicar, o browser dispara @focus ANTES de @click.
// Sem essa flag, selectItem() seria chamado duas vezes no mesmo clique
// (mesmo padrão do Segment.vue). A seleção continua no @click.
let _skipFocusFromMouse = false;

function handleAreaPointerDown(event: PointerEvent) {
    if (event.button !== 0) {
        return;
    }

    _skipFocusFromMouse = true;
    // Reset após o clique completar (focus acontece em < 50ms)
    setTimeout(() => {
        _skipFocusFromMouse = false;
    }, 50);
}

function handleFocusShelf() {
    // Ignora focus gerado por mouse — o @click cuida da seleção.
    // Só seleciona em focus por teclado (Tab).
    if (_skipFocusFromMouse) {
        return;
    }

    selection.selectItem('shelf', props.shelf.id, props.shelf, {
        section: props.section,
        lastShelf: props.lastShelf,
        firstShelf: props.firstShelf,
    });
    selectedTemplateCategoryId.value = props.shelf.template_slot?.category_id ?? null;
}

function handleSelectShelf(event: MouseEvent) {
    event.stopPropagation();
    selection.selectItem('shelf', props.shelf.id, props.shelf, {
        section: props.section,
        lastShelf: props.lastShelf,
        firstShelf: props.firstShelf,
    });
    // Sincroniza a categoria do template com o estado global para que o
    // CategoryConfigPanel realce o card correspondente.
    selectedTemplateCategoryId.value = props.shelf.template_slot?.category_id ?? null;
}

/**
 * Botão direito na área/base da prateleira: seleciona e abre o menu de
 * contexto do editor. Segmentos tratam o próprio contextmenu (com .stop),
 * então aqui só chegam cliques fora deles.
 */
function handleShelfContextMenu(event: MouseEvent) {
    if (event.ctrlKey) {
        // Gesto de Ctrl-drag do macOS — não abre menu (ver Segment.vue)
        event.preventDefault();
        event.stopPropagation();

        return;
    }

    handleSelectShelf(event);
    openContextMenu(event, 'shelf', props.shelf.id);
}
</script>

<style scoped>
/*
    Superfície do tampo (estilo "deck"): faixa clara vista em leve mergulho —
    aresta frontal (base) mais clara/próxima, fundo (topo) mais escuro/recuado.
    Fica atrás dos produtos, dando a leitura de profundidade da prateleira.
*/
.shelf-deck {
    background: linear-gradient(to bottom, #6c7885 0%, #8b98a8 55%, #a7b3c1 100%);
    border-radius: 3px 3px 0 0;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.4),
        inset 0 -2px 3px -1px rgba(0, 0, 0, 0.25);
}
</style>
