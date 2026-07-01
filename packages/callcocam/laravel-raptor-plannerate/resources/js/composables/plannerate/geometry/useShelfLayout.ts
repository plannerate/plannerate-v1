import { computed  } from 'vue';
import type {Ref} from 'vue';
import { DEFAULT_SECTION_FIELDS } from '../fields/useSectionFields';
import { calculateHolePositions } from './useSectionHoles';
import { useShelfAreaCalculation } from './useShelfAreaCalculation';
import { getShelfLevel, getZoneConfig } from './useShelfZone';
import type { Section, Shelf as ShelfType } from '@/types/planogram';

interface UseShelfLayoutOptions {
    shelf: Ref<ShelfType>;
    section: Ref<Section>;
    previousShelf: Ref<ShelfType | undefined>;
    scale: Ref<number>;
    sectionWidth: Ref<number | undefined>;
    cremalheiraWidth: Ref<number | undefined>;
    alignment: Ref<string | undefined>;
    /**
     * Número de exibição da prateleira ("Prat - N"), pré-calculado UMA vez pelo
     * pai (Shelves.vue) e passado por prop. Quando fornecido, evita o sort O(S)
     * por instância de Shelf — ver `shelfDisplayNumber`. Opcional para manter
     * compatibilidade com callers que não fornecem o mapa (ex.: PDF).
     */
    displayNumber?: Ref<number | undefined>;
}

export function useShelfLayout(options: UseShelfLayoutOptions) {
    const { calculateShelfArea } = useShelfAreaCalculation();

    const shelfHeight = computed(
        () => options.shelf.value.shelf_height * options.scale.value,
    );

    /**
     * Área da prateleira (início/altura em cm) memoizada num único computed.
     * Antes `calculateShelfArea` era chamado 2× (em `shelfAreaStyle` e
     * `shelfBasePosition`); agora ambos leem deste cache compartilhado.
     */
    const shelfArea = computed(() =>
        calculateShelfArea({
            shelf: options.shelf.value,
            previousShelf: options.previousShelf.value,
            scale: options.scale.value,
        }),
    );

    const shelfAreaStyle = computed(() => {
        const { areaStartCm, areaHeightCm } = shelfArea.value;

        return {
            top: `${areaStartCm * options.scale.value}px`,
            width: `${options.sectionWidth.value}px`,
            height: `${areaHeightCm * options.scale.value}px`,
            left: `${options.cremalheiraWidth.value}px`,
            right: `-${options.cremalheiraWidth.value}px`,
        };
    });

    const shelfBasePosition = computed(() => {
        const { areaStartCm } = shelfArea.value;

        const holePositions = calculateHolePositions(options.section.value);

        if (holePositions.length === 0) {
            const offsetFromAreaStart =
                options.shelf.value.shelf_position - areaStartCm;

            return offsetFromAreaStart * options.scale.value;
        }

        const holeHeight =
            options.section.value.hole_height ??
            DEFAULT_SECTION_FIELDS.holeHeight;
        const shelfHeightCm = options.shelf.value.shelf_height;
        const shelfPositionCm = options.shelf.value.shelf_position;

        let closestHoleIdx = 0;
        let minDistance = Math.abs(shelfPositionCm - holePositions[0]);

        for (let i = 0; i < holePositions.length; i++) {
            const distance = Math.abs(shelfPositionCm - holePositions[i]);

            if (distance < minDistance) {
                minDistance = distance;
                closestHoleIdx = i;
            }
        }

        const closestHolePos = holePositions[closestHoleIdx];
        const centeredPosition =
            closestHolePos + (holeHeight - shelfHeightCm) / 2;

        const offsetFromAreaStart = centeredPosition - areaStartCm;

        return offsetFromAreaStart * options.scale.value;
    });

    const segments = computed(
        () => options.shelf.value.segments?.filter((s) => !s.deleted_at) || [],
    );

    const isHookType = computed(
        () => options.shelf.value.product_type === 'hook',
    );

    const shelfDisplayNumber = computed(() => {
        // Caminho rápido: número fornecido pelo pai (Shelves.vue calcula o mapa
        // id→número UMA vez por seção). Evita o sort O(S) por instância de Shelf
        // — que tornava a renderização da seção O(S²) e reinvalidava todas as
        // shelves a cada mutação de segmento (pois section.shelves é reatribuído).
        const provided = options.displayNumber?.value;

        if (provided !== undefined && provided !== null) {
            return provided;
        }

        // Fallback (callers sem o mapa, ex.: PDF): cálculo local original.
        if (!options.section.value?.shelves) {
return 1;
}

        const sorted = [...options.section.value.shelves]
            .filter((s) => !s.deleted_at)
            .sort((a, b) => (b.shelf_position || 0) - (a.shelf_position || 0));

        return Math.max(
            1,
            sorted.findIndex((s) => s.id === options.shelf.value.id) + 1,
        );
    });

    const activeShelvesCount = computed(() => {
        if (!options.section.value?.shelves) return 1;
        return options.section.value.shelves.filter((s) => !s.deleted_at).length;
    });

    const shelfIndexFromTop = computed(() => {
        return Math.max(0, activeShelvesCount.value - shelfDisplayNumber.value);
    });

    const shelfZone = computed(() => {
        return getZoneConfig(getShelfLevel(shelfIndexFromTop.value, activeShelvesCount.value));
    });

    const currentAlignment = computed(
        () => options.alignment.value ?? 'justify',
    );

    /**
     * Gap uniforme (em px) usado no modo "justificar".
     *
     * Distribui o espaço livre da prateleira igualmente entre TODAS as frentes
     * (facings) de produto, ignorando o agrupamento por segmento. O resultado
     * é o mesmo espaçamento entre cada produto e também nas duas bordas — como
     * se houvesse um único segmento ocupando a prateleira inteira.
     *
     * Cálculo: gap = espaçoLivre / (totalDeFrentes + 1), onde espaçoLivre é a
     * largura da seção menos a soma das larguras de todos os produtos. Esse gap
     * é aplicado como padding-left do container, column-gap entre segmentos e
     * column-gap entre as frentes de cada layer, fazendo todos os vãos ficarem
     * idênticos.
     *
     * Retorna null quando não está justificando, quando não há largura
     * conhecida, ou quando os produtos não cabem (overflow) — nesses casos o
     * layout cai no fallback `justify-evenly`.
     */
    const justifyGap = computed<number | null>(() => {
        if (currentAlignment.value !== 'justify') {
            return null;
        }

        const sectionWidthPx = options.sectionWidth.value;

        if (!sectionWidthPx) {
            return null;
        }

        let totalFacings = 0;
        let totalProductsWidthPx = 0;

        for (const segment of segments.value) {
            const layer = segment.layer;

            if (!layer) {
                continue;
            }

            const facings = Math.max(
                1,
                Math.trunc(Number(layer.quantity ?? 1)) || 1,
            );
            const facingWidthPx =
                (Number(layer.product?.width) || 0) * options.scale.value;

            totalFacings += facings;
            totalProductsWidthPx += facings * facingWidthPx;
        }

        if (totalFacings === 0) {
            return null;
        }

        const freeSpacePx = sectionWidthPx - totalProductsWidthPx;

        if (freeSpacePx <= 0) {
            return null;
        }

        return freeSpacePx / (totalFacings + 1);
    });

    const alignmentClass = computed(() => {
        const map: Record<string, string> = {
            left: 'justify-start',
            right: 'justify-end',
            center: 'justify-center',
            justify: 'justify-evenly',
        };

        // No modo justificar com gap calculado, o espaçamento é controlado
        // manualmente (padding-left + column-gap), então alinhamos ao início.
        if (currentAlignment.value === 'justify' && justifyGap.value !== null) {
            return 'justify-start';
        }

        return map[currentAlignment.value] || 'justify-start';
    });

    /**
     * Estilo do container dos segmentos quando o gap uniforme está ativo:
     * padding-left + column-gap iguais ao gap, fazendo a borda esquerda, os
     * vãos entre segmentos e (por consequência do cálculo) a borda direita
     * ficarem idênticos.
     */
    const justifyDistributionStyle = computed(() => {
        if (justifyGap.value === null) {
            return undefined;
        }

        return {
            paddingLeft: `${justifyGap.value}px`,
            columnGap: `${justifyGap.value}px`,
        };
    });

    const segmentsPositionStyle = computed(() =>
        isHookType.value
            ? {
                  top: `${shelfBasePosition.value + shelfHeight.value}px`,
              }
            : {
                  bottom: `${shelfHeight.value}px`,
              },
    );

    return {
        shelfHeight,
        shelfAreaStyle,
        shelfBasePosition,
        segments,
        isHookType,
        shelfDisplayNumber,
        shelfIndexFromTop,
        shelfZone,
        currentAlignment,
        justifyGap,
        alignmentClass,
        justifyDistributionStyle,
        segmentsPositionStyle,
    };
}
