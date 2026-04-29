import { computed  } from 'vue';
import type {Ref} from 'vue';
import { DEFAULT_SECTION_FIELDS } from '@/composables/plannerate/useSectionFields';
import { calculateHolePositions } from '@/composables/plannerate/useSectionHoles';
import { useShelfAreaCalculation } from '@/composables/plannerate/useShelfAreaCalculation';
import type { Section, Shelf as ShelfType } from '@/types/planogram';

interface UseShelfLayoutOptions {
    shelf: Ref<ShelfType>;
    section: Ref<Section>;
    previousShelf: Ref<ShelfType | undefined>;
    scale: Ref<number>;
    sectionWidth: Ref<number | undefined>;
    cremalheiraWidth: Ref<number | undefined>;
    alignment: Ref<string | undefined>;
}

export function useShelfLayout(options: UseShelfLayoutOptions) {
    const { calculateShelfArea } = useShelfAreaCalculation();

    const shelfHeight = computed(
        () => options.shelf.value.shelf_height * options.scale.value,
    );

    const shelfAreaStyle = computed(() => {
        // Força reatividade: acessa as posições como dependências explícitas
        // (não são usadas diretamente, mas garantem que o computed recalcule)
        void options.shelf.value.shelf_position;
        void options.previousShelf.value?.shelf_position;
        void options.previousShelf.value?.shelf_height;

        const { areaStartCm, areaHeightCm } = calculateShelfArea({
            shelf: options.shelf.value,
            previousShelf: options.previousShelf.value,
            scale: options.scale.value,
        });

        return {
            top: `${areaStartCm * options.scale.value}px`,
            width: `${options.sectionWidth.value}px`,
            height: `${areaHeightCm * options.scale.value}px`,
            left: `${options.cremalheiraWidth.value}px`,
            right: `-${options.cremalheiraWidth.value}px`,
        };
    });

    const shelfBasePosition = computed(() => {
        const { areaStartCm } = calculateShelfArea({
            shelf: options.shelf.value,
            previousShelf: options.previousShelf.value,
            scale: options.scale.value,
        });

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

    const currentAlignment = computed(
        () => options.alignment.value ?? 'justify',
    );

    const isSingleSegmentJustify = computed(
        () =>
            currentAlignment.value === 'justify' && segments.value.length === 1,
    );

    const alignmentClass = computed(() => {
        const map: Record<string, string> = {
            left: 'justify-start',
            right: 'justify-end',
            center: 'justify-center',
            justify: 'justify-between',
        };

        return map[currentAlignment.value] || 'justify-start';
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
        currentAlignment,
        isSingleSegmentJustify,
        alignmentClass,
        segmentsPositionStyle,
    };
}
