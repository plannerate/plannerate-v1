<template>
    <!--
        Superfície física da prateleira com aparência pseudo-3D (profundidade).

        Componente puramente decorativo (pointer-events: none) — toda a interação
        (arraste, seleção, cadeado) permanece no <div data-shelf> de Shelf.vue,
        que envolve este componente. O estilo visual da tábua é global
        (`shelfBoardStyle`, persistido no localStorage) e pode ser sobrescrito
        pela prop `variant` (ex.: renderização de PDF/print).
    -->
    <div
        class="board pointer-events-none absolute inset-0 select-none"
        :class="[`board--${activeVariant}`, locked ? 'is-locked' : '']"
    >
        <!-- Aresta superior iluminada -->
        <span class="top" :style="{ height: `${topLip}px` }"></span>
        <!-- Lábio frontal (espessura da tábua vista de frente) -->
        <span class="lip" :style="{ height: `${frontLip}px` }"></span>
        <!-- Sombra projetada sobre os produtos da prateleira de baixo -->
        <span class="drop" :style="{ height: `${dropShadow}px` }"></span>

        <!-- Rótulo "Prat - N" -->
        <span
            v-if="displayNumber != null"
            class="label"
            :style="{ fontSize: `${labelFontSize}px` }"
        >
            Prat - {{ displayNumber }}
        </span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { shelfBoardStyle } from '../../../composables/plannerate/core/useGondolaState';
import type { ShelfBoardStyle } from '../../../composables/plannerate/core/useGondolaState';

interface Props {
    /** Espessura visível da tábua em px (`shelf_height` em cm × escala). */
    height: number;
    /** Escala atual do editor — dimensiona o rótulo e as arestas. */
    scale: number;
    /** Número de exibição "Prat - N" (pré-calculado por Shelves.vue). */
    displayNumber?: number;
    /** Prateleira travada contra geração automática (realce âmbar). */
    locked?: boolean;
    /** Sobrescreve o estilo global (ex.: PDF/print). Sem valor = usa o global. */
    variant?: ShelfBoardStyle;
}

const props = defineProps<Props>();

/** Estilo efetivo: prop tem prioridade; senão cai no estado global. */
const activeVariant = computed<ShelfBoardStyle>(() => props.variant ?? shelfBoardStyle.value);

/** Aresta superior iluminada: proporcional à espessura, com teto para não dominar. */
const topLip = computed(() => Math.max(1, Math.min(props.height * 0.35, 4)));

/** Lábio frontal (espessura vista de frente): a maior parte da tábua abaixo do topo. */
const frontLip = computed(() => Math.max(2, Math.min(props.height * 0.5, 7)));

/** Sombra projetada sobre os produtos da prateleira de baixo — cresce com a escala. */
const dropShadow = computed(() => Math.max(3, Math.min(props.scale * 2, 10)));

/** Mesma fórmula de tamanho de fonte usada antes em Shelf.vue. */
const labelFontSize = computed(() => Math.max(8, Math.min(16, (10 * props.scale) / 3)));
</script>

<style scoped>
.board {
    border-radius: 2px;
}

/* Camadas comuns */
.board .top {
    position: absolute;
    inset: 0 0 auto 0;
    z-index: 1;
}
.board .lip {
    position: absolute;
    inset: auto 0 0 0;
    z-index: 1;
}
.board .drop {
    position: absolute;
    inset: auto 0 0 0;
    transform: translateY(100%);
    z-index: -1;
    background: linear-gradient(rgba(0, 0, 0, 0.32), transparent);
}
.board .label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 3;
    font-weight: 640;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

/* ─── 1 · Slate metálico (padrão) ───────────────────────────── */
.board--slate {
    background: linear-gradient(#64707f, #3f4855 42%, #1f242c);
    box-shadow: 0 4px 7px -2px rgba(0, 0, 0, 0.5);
}
.board--slate .top {
    background: linear-gradient(rgba(255, 255, 255, 0.42), transparent);
}
.board--slate .lip {
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.5));
}
.board--slate .label {
    color: #dce1e8;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.6);
}

/* ─── 2 · Madeira (carvalho claro) ──────────────────────────── */
.board--wood {
    background:
        repeating-linear-gradient(90deg, rgba(90, 52, 20, 0) 0 5px, rgba(90, 52, 20, 0.1) 5px 6px),
        linear-gradient(#c79a63, #a6743f 45%, #7c5027);
    box-shadow: 0 4px 7px -2px rgba(60, 30, 0, 0.45);
}
.board--wood .top {
    background: linear-gradient(rgba(255, 244, 225, 0.6), transparent);
}
.board--wood .lip {
    background: linear-gradient(transparent, rgba(60, 32, 8, 0.5));
}
.board--wood .label {
    color: #3c2410;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.25);
}

/* ─── 3 · Aço varejo branco ─────────────────────────────────── */
.board--white {
    background: linear-gradient(#ffffff, #eef1f5 40%, #c4cbd4);
    box-shadow: 0 4px 8px -3px rgba(30, 40, 60, 0.32);
}
.board--white .top {
    background: linear-gradient(#ffffff, transparent);
}
.board--white .lip {
    background: linear-gradient(transparent, rgba(120, 132, 148, 0.55));
    border-bottom: 1px solid rgba(90, 100, 116, 0.5);
}
.board--white .label {
    color: #48505c;
}

/* ─── 4 · Cromado polido ────────────────────────────────────── */
.board--chrome {
    background: linear-gradient(#eef3f8, #aeb9c6 30%, #7d8896 50%, #b7c1cd 70%, #e6ecf2);
    box-shadow: 0 4px 8px -2px rgba(20, 30, 45, 0.5);
}
.board--chrome .top {
    background: linear-gradient(rgba(255, 255, 255, 0.85), transparent);
}
.board--chrome .lip {
    background: linear-gradient(transparent, rgba(20, 30, 45, 0.5));
}
.board--chrome .label {
    color: #2b333f;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
}

/* ─── 5 · Relevo 3D (borda arredondada / bullnose) ──────────── */
/*
    Profundidade SEM nada saliente acima da tábua (para nunca cobrir o produto):
    o gradiente de várias paradas simula uma borda cilíndrica vista de frente —
    faixa especular no alto + escurecimento na base = leitura 3D de relevo.
*/
.board--persp {
    background: linear-gradient(
        to bottom,
        #566270 0%,
        #8e9cae 13%,
        #47515e 40%,
        #2b323b 74%,
        #12161b 100%
    );
    box-shadow:
        0 5px 9px -3px rgba(0, 0, 0, 0.55),
        inset 0 0 0 1px rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}
.board--persp .top {
    background: linear-gradient(rgba(255, 255, 255, 0.5), transparent);
}
.board--persp .lip {
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.5));
}
.board--persp .label {
    color: #eef2f6;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.7);
}

/* ─── 6 · Tampo 3D (face frontal escura; o tampo claro é ─────
    renderizado por Shelf.vue ATRÁS dos produtos) ────────────── */
.board--deck {
    background: linear-gradient(#39424e, #262d36 55%, #12161b);
    box-shadow: 0 4px 7px -2px rgba(0, 0, 0, 0.5);
}
.board--deck .top {
    background: linear-gradient(rgba(255, 255, 255, 0.18), transparent);
}
.board--deck .lip {
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.5));
}
.board--deck .label {
    color: #dce1e8;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.6);
}

/* ─── 7 · Vidro / acrílico ──────────────────────────────────── */
.board--glass {
    background: linear-gradient(rgba(214, 230, 244, 0.55), rgba(150, 182, 214, 0.42) 50%, rgba(96, 132, 170, 0.5));
    box-shadow:
        0 4px 10px -3px rgba(40, 70, 110, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.45);
}
.board--glass .top {
    background: linear-gradient(rgba(255, 255, 255, 0.65), transparent);
}
.board--glass .lip {
    background: linear-gradient(transparent, rgba(40, 70, 110, 0.35));
}
.board--glass .drop {
    background: linear-gradient(rgba(40, 70, 110, 0.18), transparent);
}
.board--glass .label {
    color: #244a70;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
}

/* Realce quando a prateleira está travada (independe do estilo) */
.board.is-locked {
    outline: 1px solid rgba(251, 191, 36, 0.7);
    outline-offset: -1px;
}

/* Clareia a tábua no hover da prateleira (grupo definido em Shelf.vue) */
:global(.group\/shelf:hover) .board {
    filter: brightness(1.08);
}
</style>
