<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useT } from '@/composables/useT';
import { useBcgLabels } from './labels';
import type { BcgQuadrant, BcgResult } from './types';

/**
 * Matriz BCG — dispersão com bolha proporcional ao espaço de gôndola.
 *
 * DECISÃO DE CORREÇÃO: o gráfico mostra UM GRUPO POR VEZ.
 * As linhas de corte (mediana/média) são calculadas POR GRUPO mercadológico. Se a
 * gôndola tem três categorias, há três medianas diferentes — desenhar todos os
 * produtos num único 2×2 com uma única linha de corte mostraria produtos do lado
 * "errado" da sua própria linha. Um seletor de grupo mantém eixos em unidades reais
 * (R$, unidades) e a linha de corte verdadeira.
 *
 * DIMENSIONAMENTO 1:1 (uma unidade do viewBox = um pixel).
 * Antes o viewBox era fixo (820×470) e esticado até a largura do container. Isso
 * escala TUDO junto: num painel largo a fonte de 10px virava ~20px e a altura passava
 * de 800px, estourando o painel e cortando o título do eixo Y. Medindo o container
 * com ResizeObserver e usando a largura real no viewBox, o texto sai no tamanho que
 * foi pedido e a altura fica sob controle.
 *
 * CANAIS DE CODIFICAÇÃO:
 *   - posição  → quadrante (é o que um 2×2 significa; a cor apenas REFORÇA)
 *   - cor      → quadrante (redundante com a posição, de propósito: nunca cor sozinha)
 *   - área     → share de gôndola (raio ∝ √share, para a ÁREA ser proporcional)
 *
 * Paleta validada com scripts/validate_palette.js nos 6 pares (não só nos adjacentes),
 * contra as superfícies reais (#ffffff / #111827). Claro: todos PASS (pior par
 * verde×vermelho ΔE 13,3). Escuro: um par na banda 8–12 (amarelo×verde, ΔE 10,3 sob
 * protanopia) — permitido porque a posição carrega o quadrante independentemente da cor.
 */

interface Props {
    results: BcgResult[];
    /** Altura útil do gráfico em px. A modal expandida passa um valor maior. */
    height?: number;
}

const props = withDefaults(defineProps<Props>(), {
    height: 440,
});

const { t } = useT();
const { axisLabel, quadrantLabel, quadrantIcon, spaceActionLabel, spaceActionIcon } = useBcgLabels();

// ─── Medição do container (1 unidade do viewBox = 1 px) ───────────────────────

const container = ref<HTMLElement | null>(null);
const width = ref(820);

let observer: ResizeObserver | null = null;

onMounted(() => {
    if (!container.value || typeof ResizeObserver === 'undefined') {
return;
}

    observer = new ResizeObserver((entries) => {
        const measured = entries[0]?.contentRect.width ?? 0;
        // Piso para o gráfico não colapsar em painéis muito estreitos
        width.value = Math.max(420, Math.round(measured));
    });

    observer.observe(container.value);
});

onBeforeUnmount(() => observer?.disconnect());

// ─── Geometria ────────────────────────────────────────────────────────────────
// A altura inclui a faixa do eixo X: fixar só a área de plotagem cortaria os rótulos.
// A direita tem folga porque o rótulo da linha de corte do eixo Y mora LÁ (fora do
// plot) — dentro dele, ele atropelava as bolhas.
const PAD = { top: 26, right: 72, bottom: 52, left: 68 };

const plotW = computed(() => width.value - PAD.left - PAD.right);
const plotH = computed(() => props.height - PAD.top - PAD.bottom);

const R_MIN = 5;
const R_MAX = 22;

const QUADRANTS: BcgQuadrant[] = ['alto_alto', 'forte_x', 'forte_y', 'baixo_baixo'];

// ─── Grupo selecionado ────────────────────────────────────────────────────────

/** Grupos presentes no resultado, do maior para o menor. */
const groups = computed(() => {
    const byId = new Map<string, { id: string; name: string; count: number }>();

    for (const item of props.results) {
        const id = item.group_id ?? '__sem_grupo__';
        const existing = byId.get(id);

        if (existing) {
            existing.count += 1;
        } else {
            byId.set(id, {
                id,
                name: item.group_name || t('plannerate.analysis.bcg_chart.no_group'),
                count: 1,
            });
        }
    }

    return [...byId.values()].sort((a, b) => b.count - a.count);
});

const selectedGroupId = ref<string | null>(null);

const activeGroupId = computed(() => selectedGroupId.value ?? groups.value[0]?.id ?? null);

const activeGroupName = computed(
    () => groups.value.find((g) => g.id === activeGroupId.value)?.name ?? '',
);

/** Produtos do grupo ativo. Sem venda ficam fora: não têm posição real na matriz. */
const points = computed(() =>
    props.results.filter(
        (item) => (item.group_id ?? '__sem_grupo__') === activeGroupId.value && !item.sem_venda,
    ),
);

const hiddenNoSalesCount = computed(
    () =>
        props.results.filter(
            (item) => (item.group_id ?? '__sem_grupo__') === activeGroupId.value && item.sem_venda,
        ).length,
);

// ─── Escalas ──────────────────────────────────────────────────────────────────

/** Domínio com 8% de folga de cada lado, sempre incluindo a linha de corte. */
const domain = (values: number[], threshold: number): [number, number] => {
    const all = [...values, threshold];
    let min = Math.min(...all);
    let max = Math.max(...all);

    if (min === max) {
        // Grupo sem dispersão: abre uma janela artificial para os pontos não colapsarem na borda
        const delta = Math.abs(min) || 1;
        min -= delta;
        max += delta;
    }

    const pad = (max - min) * 0.08;

    return [min - pad, max + pad];
};

const xThreshold = computed(() => points.value[0]?.x_threshold ?? 0);
const yThreshold = computed(() => points.value[0]?.y_threshold ?? 0);

const xDomain = computed(() => domain(points.value.map((p) => p.x_value), xThreshold.value));
const yDomain = computed(() => domain(points.value.map((p) => p.y_value), yThreshold.value));

const scaleX = (value: number): number => {
    const [min, max] = xDomain.value;

    return PAD.left + ((value - min) / (max - min)) * plotW.value;
};

const scaleY = (value: number): number => {
    const [min, max] = yDomain.value;

    // SVG cresce para baixo: inverte para o eixo Y apontar para cima
    return PAD.top + plotH.value - ((value - min) / (max - min)) * plotH.value;
};

const maxShare = computed(() => Math.max(...points.value.map((p) => p.share_gondola), 0));

/** Raio ∝ √share para que a ÁREA da bolha seja proporcional ao espaço ocupado. */
const radius = (item: BcgResult): number => {
    if (item.sem_dimensao || maxShare.value <= 0) {
return R_MIN + 1;
}

    return R_MIN + (R_MAX - R_MIN) * Math.sqrt(Math.max(item.share_gondola, 0) / maxShare.value);
};

const cutX = computed(() => scaleX(xThreshold.value));
const cutY = computed(() => scaleY(yThreshold.value));

/** Rótulo do corte no topo, preso às bordas para não vazar do gráfico. */
const cutXLabelX = computed(() =>
    Math.min(Math.max(cutX.value, PAD.left + 34), PAD.left + plotW.value - 34),
);

// ─── Ticks ────────────────────────────────────────────────────────────────────

const TICK_COUNT = 4;

const ticks = (dmn: [number, number]): number[] => {
    const [min, max] = dmn;
    const step = (max - min) / TICK_COUNT;

    return Array.from({ length: TICK_COUNT + 1 }, (_, i) => min + step * i);
};

const xTicks = computed(() => ticks(xDomain.value));
const yTicks = computed(() => ticks(yDomain.value));

const compact = new Intl.NumberFormat('pt-BR', { notation: 'compact', maximumFractionDigits: 1 });
const full = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });

// ─── Rótulos diretos ──────────────────────────────────────────────────────────

const truncate = (text: string, max = 18): string =>
    text.length > max ? `${text.slice(0, max - 1)}…` : text;

/**
 * Caixa do rótulo com folga. A fonte tem 10px, mas o halo da superfície adiciona 3px
 * em volta — medir só a altura da fonte fazia duas caixas "não colidirem" por 1px
 * enquanto visualmente se sobrepunham.
 */
const LABEL_H = 16;
const LABEL_PAD_X = 8;
const MAX_LABELS = 4;

/**
 * Rotula APENAS os acionáveis, no máximo 4: um número em cada ponto seria caos e
 * ninguém leria. Quem precisa de mais lê a tabela logo abaixo.
 *
 * Dois cuidados que só apareceram ao RENDERIZAR e olhar:
 *   - produtos acionáveis costumam estar próximos entre si (o canto fraco da matriz
 *     é aglomerado), e os rótulos se sobrepunham — um rótulo que colide é descartado
 *     em favor do produto de maior share;
 *   - um ponto colado no topo tinha o rótulo cortado pela borda — nesse caso ele
 *     desce para baixo da bolha.
 */
const labelledPoints = computed(() => {
    const candidates = points.value
        .filter((p) => p.acao_espaco === 'aumentar' || p.acao_espaco === 'reduzir')
        .sort((a, b) => b.share_gondola - a.share_gondola);

    const placed: Array<{ x: number; y: number; w: number; h: number }> = [];
    const result: Array<{ item: BcgResult; text: string; x: number; y: number }> = [];

    for (const item of candidates) {
        if (result.length >= MAX_LABELS) {
break;
}

        const text = truncate(item.product_name);
        const labelWidth = text.length * 5.6 + LABEL_PAD_X;
        const cx = scaleX(item.x_value);
        const cy = scaleY(item.y_value);
        const r = radius(item);

        // Acima da bolha; se estourar o topo do gráfico, vai para baixo dela
        let y = cy - r - 5;

        if (y - LABEL_H < PAD.top) {
            y = cy + r + 11;
        }

        const box = { x: cx - labelWidth / 2, y: y - LABEL_H, w: labelWidth, h: LABEL_H };

        const collides = placed.some(
            (other) =>
                box.x < other.x + other.w &&
                other.x < box.x + box.w &&
                box.y < other.y + other.h &&
                other.y < box.y + box.h,
        );

        if (collides) {
continue;
}

        placed.push(box);
        result.push({ item, text, x: cx, y });
    }

    return result;
});

// ─── Hover / foco ─────────────────────────────────────────────────────────────

const hovered = ref<BcgResult | null>(null);

const xAxis = computed(() => points.value[0]?.x_axis ?? 'quantidade');
const yAxis = computed(() => points.value[0]?.y_axis ?? 'margem');

/** Com o viewBox 1:1, as coordenadas do SVG JÁ são pixels do container. */
const tooltipStyle = computed(() => {
    if (!hovered.value) {
return {};
}

    const px = scaleX(hovered.value.x_value);
    const py = scaleY(hovered.value.y_value);
    const r = radius(hovered.value);

    return {
        left: `${px}px`,
        top: `${py - r - 8}px`,
        // Perto da borda direita o tooltip vira para dentro
        transform: px > width.value * 0.7 ? 'translate(-100%, -100%)' : 'translate(-50%, -100%)',
    };
});
</script>

<template>
    <div ref="container" class="bcg-chart">
        <!-- Cabeçalho: grupo + legenda -->
        <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-2">
            <div v-if="groups.length > 1" class="flex items-center gap-1.5">
                <label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.bcg_chart.group') }}</label>
                <select
                    :value="activeGroupId"
                    class="h-7 rounded-md border border-input bg-background px-2 text-[11px] shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    @change="selectedGroupId = ($event.target as HTMLSelectElement).value"
                >
                    <option v-for="group in groups" :key="group.id" :value="group.id">
                        {{ group.name }} ({{ group.count }})
                    </option>
                </select>
            </div>
            <p v-else class="text-[11px] font-medium text-foreground">{{ activeGroupName }}</p>

            <!-- Legenda: sempre presente (4 séries) -->
            <ul class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <li v-for="quadrant in QUADRANTS" :key="quadrant" class="flex items-center gap-1">
                    <span class="size-2.5 rounded-full" :style="{ background: `var(--q-${quadrant})` }" />
                    <span class="text-[10px] text-muted-foreground">
                        {{ quadrantIcon(quadrant) }} {{ quadrantLabel(quadrant, xAxis, yAxis) }}
                    </span>
                </li>
                <li class="flex items-center gap-1 border-l border-border pl-3">
                    <span class="size-1.5 rounded-full bg-muted-foreground/50" />
                    <span class="size-3 rounded-full bg-muted-foreground/50" />
                    <span class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.bcg_chart.bubble_legend') }}</span>
                </li>
            </ul>
        </div>

        <div v-if="points.length === 0" class="rounded-lg border border-dashed border-border bg-accent/20 p-6 text-center text-xs text-muted-foreground">
            {{ t('plannerate.analysis.bcg_chart.empty') }}
        </div>

        <div v-else class="relative">
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                :height="height"
                class="w-full"
                role="img"
                :aria-label="t('plannerate.analysis.bcg_chart.aria')"
            >
                <!-- Washes dos quadrantes: dão a estrutura do 2×2, bem recessivos -->
                <rect :x="cutX" :y="PAD.top" :width="PAD.left + plotW - cutX" :height="cutY - PAD.top" fill="var(--q-alto_alto)" opacity="0.06" />
                <rect :x="cutX" :y="cutY" :width="PAD.left + plotW - cutX" :height="PAD.top + plotH - cutY" fill="var(--q-forte_x)" opacity="0.06" />
                <rect :x="PAD.left" :y="PAD.top" :width="cutX - PAD.left" :height="cutY - PAD.top" fill="var(--q-forte_y)" opacity="0.06" />
                <rect :x="PAD.left" :y="cutY" :width="cutX - PAD.left" :height="PAD.top + plotH - cutY" fill="var(--q-baixo_baixo)" opacity="0.06" />

                <!-- Eixos: hairlines sólidos, recessivos -->
                <line :x1="PAD.left" :y1="PAD.top + plotH" :x2="PAD.left + plotW" :y2="PAD.top + plotH" stroke="var(--axis)" stroke-width="1" />
                <line :x1="PAD.left" :y1="PAD.top" :x2="PAD.left" :y2="PAD.top + plotH" stroke="var(--axis)" stroke-width="1" />

                <!-- Ticks -->
                <g class="tick-text" text-anchor="middle">
                    <text v-for="(tick, i) in xTicks" :key="`xt-${i}`" :x="scaleX(tick)" :y="PAD.top + plotH + 16">
                        {{ compact.format(tick) }}
                    </text>
                </g>
                <g class="tick-text" text-anchor="end">
                    <text v-for="(tick, i) in yTicks" :key="`yt-${i}`" :x="PAD.left - 8" :y="scaleY(tick) + 3.5">
                        {{ compact.format(tick) }}
                    </text>
                </g>

                <!-- Rótulos dos eixos: nomeiam a MÉTRICA, nunca "Eixo X" -->
                <text class="axis-title" :x="PAD.left + plotW / 2" :y="height - 12" text-anchor="middle">
                    {{ axisLabel(xAxis) }}
                </text>
                <text
                    class="axis-title"
                    :x="14"
                    :y="PAD.top + plotH / 2"
                    text-anchor="middle"
                    :transform="`rotate(-90 14 ${PAD.top + plotH / 2})`"
                >
                    {{ axisLabel(yAxis) }}
                </text>

                <!-- Linhas de corte: tracejadas porque SÃO limiares (não grade) -->
                <line :x1="cutX" :y1="PAD.top" :x2="cutX" :y2="PAD.top + plotH" stroke="var(--cut)" stroke-width="1.5" stroke-dasharray="5 4" />
                <line :x1="PAD.left" :y1="cutY" :x2="PAD.left + plotW" :y2="cutY" stroke="var(--cut)" stroke-width="1.5" stroke-dasharray="5 4" />

                <!--
                    Rótulos das linhas de corte FORA da área de plotagem (topo e direita).
                    Dentro dela não existe canto seguro: à direita moram os outliers de
                    venda e à esquerda os produtos fracos — em qualquer posição o rótulo
                    acabava por cima de uma bolha.
                -->
                <text class="cut-text" :x="cutXLabelX" :y="PAD.top - 7" text-anchor="middle">
                    {{ t('plannerate.analysis.bcg_chart.cut_line') }} {{ full.format(xThreshold) }}
                </text>
                <text class="cut-text" :x="PAD.left + plotW + 6" :y="cutY + 3">
                    {{ full.format(yThreshold) }}
                </text>

                <!-- Bolhas -->
                <g v-for="item in points" :key="item.product_id">
                    <!-- Sem dimensão: anel vazado. Uma bolha pequena mentiria "ocupa pouco espaço" -->
                    <circle
                        :cx="scaleX(item.x_value)"
                        :cy="scaleY(item.y_value)"
                        :r="radius(item)"
                        :fill="item.sem_dimensao ? 'none' : `var(--q-${item.quadrant})`"
                        :fill-opacity="item.sem_dimensao ? 0 : 0.75"
                        :stroke="item.sem_dimensao ? `var(--q-${item.quadrant})` : 'var(--surface)'"
                        :stroke-width="item.sem_dimensao ? 1.5 : 2"
                        :stroke-dasharray="item.sem_dimensao ? '3 2' : undefined"
                    />

                    <!-- Alvo de clique/hover ≥ 24px: ninguém acerta um ponto de 8px no centro -->
                    <circle
                        :cx="scaleX(item.x_value)"
                        :cy="scaleY(item.y_value)"
                        :r="Math.max(radius(item) + 8, 12)"
                        fill="transparent"
                        tabindex="0"
                        class="hit"
                        :aria-label="`${item.product_name}: ${quadrantLabel(item.quadrant, item.x_axis, item.y_axis)}`"
                        @mouseenter="hovered = item"
                        @mouseleave="hovered = null"
                        @focus="hovered = item"
                        @blur="hovered = null"
                    />
                </g>

                <!-- Rótulos diretos: só nos acionáveis, sem colisão, acima das bolhas -->
                <text
                    v-for="label in labelledPoints"
                    :key="`lbl-${label.item.product_id}`"
                    class="point-label"
                    :x="label.x"
                    :y="label.y"
                    text-anchor="middle"
                >
                    {{ label.text }}
                </text>
            </svg>

            <!-- Tooltip: reforça, nunca é a única via — a tabela abaixo tem tudo -->
            <div
                v-if="hovered"
                class="pointer-events-none absolute z-20 w-56 rounded-md border border-border bg-popover p-2 text-[11px] shadow-lg"
                :style="tooltipStyle"
            >
                <p class="line-clamp-2 font-semibold text-foreground">{{ hovered.product_name }}</p>
                <p class="mt-0.5 font-mono text-[10px] text-muted-foreground">{{ hovered.ean }}</p>

                <div class="mt-1.5 flex items-center gap-1">
                    <span class="size-2 rounded-full" :style="{ background: `var(--q-${hovered.quadrant})` }" />
                    <span class="font-medium text-foreground">
                        {{ quadrantLabel(hovered.quadrant, hovered.x_axis, hovered.y_axis) }}
                    </span>
                </div>

                <dl class="mt-1.5 space-y-0.5 border-t border-border pt-1.5">
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">{{ axisLabel(hovered.x_axis) }}</dt>
                        <dd class="tabular-nums text-foreground">{{ full.format(hovered.x_value) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">{{ axisLabel(hovered.y_axis) }}</dt>
                        <dd class="tabular-nums text-foreground">{{ full.format(hovered.y_value) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.facings') }}</dt>
                        <dd class="tabular-nums text-foreground">{{ hovered.facings }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.share_gondola') }}</dt>
                        <dd class="tabular-nums text-foreground">
                            {{ hovered.sem_dimensao ? '—' : `${full.format(hovered.share_gondola)}%` }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2 border-t border-border pt-1">
                        <dt class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.action') }}</dt>
                        <dd class="font-semibold text-foreground">
                            {{ spaceActionIcon(hovered.acao_espaco) }} {{ spaceActionLabel(hovered.acao_espaco) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <p v-if="hiddenNoSalesCount > 0" class="mt-1 text-[10px] text-muted-foreground">
            {{ hiddenNoSalesCount }} {{ t('plannerate.analysis.bcg_chart.hidden_no_sales') }}
        </p>
    </div>
</template>

<style scoped>
/*
 * Cores por PAPEL, não por hex solto: a troca claro/escuro acontece num lugar só.
 * Os tons vieram da paleta de referência e foram validados com o script do skill de
 * dataviz contra as superfícies reais do app (#ffffff / #111827).
 */
.bcg-chart {
    --q-alto_alto: #008300;
    --q-forte_x: #2a78d6;
    --q-forte_y: #eda100;
    --q-baixo_baixo: #e34948;
    --surface: #ffffff;
    --axis: #c3c2b7;
    --cut: #898781;
    --ink-muted: #898781;
    --ink-primary: #0b0b0b;
}

/* O app alterna tema por classe (.dark), então prefers-color-scheme não basta. */
:global(.dark) .bcg-chart {
    --q-alto_alto: #008300;
    --q-forte_x: #3987e5;
    --q-forte_y: #c98500;
    --q-baixo_baixo: #e66767;
    --surface: #111827;
    --axis: #383835;
    --cut: #898781;
    --ink-muted: #898781;
    --ink-primary: #ffffff;
}

.tick-text {
    font-size: 10px;
    font-variant-numeric: tabular-nums;
    fill: var(--ink-muted);
}

.axis-title {
    font-size: 11px;
    font-weight: 600;
    fill: var(--ink-muted);
}

.cut-text {
    font-size: 9px;
    font-variant-numeric: tabular-nums;
    fill: var(--ink-muted);
    paint-order: stroke;
    stroke: var(--surface);
    stroke-width: 3px;
    stroke-linejoin: round;
}

.point-label {
    font-size: 10px;
    font-weight: 600;
    fill: var(--ink-primary);
    paint-order: stroke;
    stroke: var(--surface);
    stroke-width: 3px;
    stroke-linejoin: round;
}

.hit {
    cursor: pointer;
    outline: none;
}

.hit:focus-visible {
    stroke: var(--ink-primary);
    stroke-width: 2;
}
</style>
