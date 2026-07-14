<!--
    Placeholder do produto sem imagem.

    `max-w-full` + `preserveAspectRatio="xMidYMid slice"` reproduzem exatamente o
    comportamento do <img class="object-cover"> que ele substitui: o preflight do
    Tailwind dá `max-width: 100%` a `img` mas NÃO a `svg`, então sem essa classe o
    SVG vira um item de flex de largura rígida e estoura a prateleira quando o
    usuário passa do limite de frentes com Shift (as frentes são comprimidas pelo
    flex). Com `slice`, o desenho é recortado ao centro em vez de distorcer.
-->
<template>
    <svg
        :width="boxWidth"
        :height="boxHeight"
        :viewBox="`0 0 ${boxWidth} ${boxHeight}`"
        preserveAspectRatio="xMidYMid slice"
        role="img"
        :aria-label="label"
        class="block max-w-full select-none"
    >
        <!-- Tooltip nativa do navegador: o nome (e o EAN) aparecem no hover,
             mesmo quando a caixa é pequena demais para exibir o texto. -->
        <title>{{ tooltip }}</title>

        <defs>
            <linearGradient :id="`${uid}-bg`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f8fafc" />
                <stop offset="100%" stop-color="#e2e8f0" />
            </linearGradient>

            <pattern
                :id="`${uid}-hatch`"
                patternUnits="userSpaceOnUse"
                :width="hatchStep"
                :height="hatchStep"
                patternTransform="rotate(45)"
            >
                <line
                    x1="0"
                    y1="0"
                    x2="0"
                    :y2="hatchStep"
                    stroke="#cbd5e1"
                    :stroke-width="hatchStroke"
                />
            </pattern>

            <clipPath :id="`${uid}-clip`">
                <rect
                    x="0"
                    y="0"
                    :width="boxWidth"
                    :height="boxHeight"
                    :rx="radius"
                />
            </clipPath>
        </defs>

        <g :clip-path="`url(#${uid}-clip)`">
            <rect
                :width="boxWidth"
                :height="boxHeight"
                :fill="`url(#${uid}-bg)`"
            />
            <rect
                :width="boxWidth"
                :height="boxHeight"
                :fill="`url(#${uid}-hatch)`"
                opacity="0.45"
            />
        </g>

        <rect
            :x="border / 2"
            :y="border / 2"
            :width="Math.max(0, boxWidth - border)"
            :height="Math.max(0, boxHeight - border)"
            :rx="radius"
            fill="none"
            stroke="#94a3b8"
            :stroke-width="border"
            :stroke-dasharray="`${border * 3} ${border * 2}`"
        />

        <!-- Ícone de caixa: viewBox próprio, então nunca distorce nem some em
             caixas estreitas — só é omitido quando não há espaço útil. -->
        <svg
            v-if="showIcon"
            :x="iconX"
            :y="iconY"
            :width="iconSize"
            :height="iconSize"
            viewBox="0 0 24 24"
            fill="none"
            stroke="#94a3b8"
            stroke-width="1.6"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M21 8.5 12 3.5 3 8.5v7l9 5 9-5v-7Z" />
            <path d="m3 8.5 9 5 9-5" />
            <path d="M12 13.5v7" />
            <path d="m7.5 6 9 5" />
        </svg>

        <text
            v-if="showName"
            :x="boxWidth / 2"
            :y="nameY"
            text-anchor="middle"
            dominant-baseline="middle"
            :font-size="nameFontSize"
            font-family="system-ui, -apple-system, sans-serif"
            font-weight="600"
            fill="#64748b"
        >
            {{ nameText }}
        </text>

        <text
            v-if="showEan"
            :x="boxWidth / 2"
            :y="eanY"
            text-anchor="middle"
            dominant-baseline="middle"
            :font-size="eanFontSize"
            font-family="ui-monospace, SFMono-Regular, monospace"
            fill="#94a3b8"
        >
            {{ ean }}
        </text>
    </svg>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue';
import { useT } from '@/composables/useT';

interface Props {
    /** Largura da caixa do produto, em px. */
    width: number;
    /** Altura da caixa do produto, em px. */
    height: number;
    name?: string | null;
    ean?: string | null;
}

const props = defineProps<Props>();
const { t } = useT();
const uid = useId();

const clamp = (value: number, min: number, max: number): number =>
    Math.min(max, Math.max(min, value));

const boxWidth = computed(() => Math.max(4, Math.round(props.width || 20)));
const boxHeight = computed(() => Math.max(4, Math.round(props.height || 20)));
const smallestSide = computed(() => Math.min(boxWidth.value, boxHeight.value));

const radius = computed(() => clamp(smallestSide.value * 0.1, 1, 6));
const border = computed(() => clamp(smallestSide.value * 0.03, 0.5, 1.5));
const hatchStep = computed(() => clamp(smallestSide.value * 0.16, 3, 10));
const hatchStroke = computed(() => clamp(hatchStep.value * 0.14, 0.4, 1.2));

const label = computed(
    () => props.name || t('plannerate.editor.layer.no_image'),
);

/** Texto da tooltip: nome (ou "Sem imagem") e, quando houver, o EAN. */
const tooltip = computed(() => {
    const parts = [label.value];

    if (props.ean) {
        parts.push(`EAN ${props.ean}`);
    }

    if (props.name) {
        parts.push(t('plannerate.editor.layer.no_image'));
    }

    return parts.join(' · ');
});

/**
 * O nome só aparece quando cabe pelo menos uma linha legível; abaixo disso o
 * ícone ocupa a caixa inteira. Isso mantém o placeholder limpo em facings
 * minúsculos (produtos baixos ou muito estreitos).
 */
const showName = computed(
    () => Boolean(props.name) && boxHeight.value >= 46 && boxWidth.value >= 28,
);
const showEan = computed(
    () => Boolean(props.ean) && boxHeight.value >= 72 && boxWidth.value >= 54,
);

const nameFontSize = computed(() => clamp(smallestSide.value * 0.16, 6, 10));
const eanFontSize = computed(() => nameFontSize.value * 0.85);

const textBlockHeight = computed(() => {
    if (!showName.value) {
        return 0;
    }

    const lines = nameFontSize.value * 1.5 + (showEan.value ? eanFontSize.value * 1.5 : 0);

    return lines;
});

const showIcon = computed(
    () => smallestSide.value >= 12 && boxHeight.value - textBlockHeight.value >= 14,
);

const iconSize = computed(() =>
    clamp(
        Math.min(
            boxWidth.value * 0.55,
            (boxHeight.value - textBlockHeight.value) * 0.62,
        ),
        8,
        56,
    ),
);
const iconX = computed(() => (boxWidth.value - iconSize.value) / 2);
const iconY = computed(
    () => (boxHeight.value - textBlockHeight.value - iconSize.value) / 2,
);

const nameY = computed(() => {
    if (!showIcon.value) {
        return boxHeight.value / 2 - (showEan.value ? eanFontSize.value * 0.75 : 0);
    }

    return iconY.value + iconSize.value + nameFontSize.value * 0.95;
});
const eanY = computed(() => nameY.value + nameFontSize.value * 1.35);

/** Trunca pela largura útil da caixa — SVG não quebra/recorta texto sozinho. */
const nameText = computed(() => {
    const name = (props.name ?? '').trim();
    const maxChars = Math.floor(
        (boxWidth.value - border.value * 4) / (nameFontSize.value * 0.6),
    );

    if (maxChars <= 1) {
        return '';
    }

    if (name.length <= maxChars) {
        return name;
    }

    return `${name.slice(0, Math.max(1, maxChars - 1))}…`;
});
</script>
