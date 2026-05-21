import type { Ref } from 'vue';

export type ShelfLevel = 'high' | 'eye' | 'hand' | 'low';

export interface ShelfZone {
    level: ShelfLevel;
    label: string;
    labelShort: string;
    colorClass: string;
    bgClass: string;
    textClass: string;
    borderClass: string;
}

const ZONE_CONFIG: Record<ShelfLevel, ShelfZone> = {
    high: {
        level: 'high',
        label: 'Zona Fria — Acima dos olhos',
        labelShort: 'Fria',
        colorClass: 'zone-cold',
        bgClass: 'bg-blue-50 dark:bg-blue-950/20',
        textClass: 'text-blue-400',
        borderClass: 'bg-blue-300',
    },
    eye: {
        level: 'eye',
        label: 'Zona Quente — Nível dos olhos',
        labelShort: 'Quente',
        colorClass: 'zone-hot',
        bgClass: 'bg-red-50 dark:bg-red-950/20',
        textClass: 'text-red-500',
        borderClass: 'bg-red-400',
    },
    hand: {
        level: 'hand',
        label: 'Zona Quente — Nível das mãos',
        labelShort: 'Quente',
        colorClass: 'zone-warm',
        bgClass: 'bg-amber-50 dark:bg-amber-950/20',
        textClass: 'text-amber-500',
        borderClass: 'bg-amber-400',
    },
    low: {
        level: 'low',
        label: 'Zona Fria — Nível do chão',
        labelShort: 'Fria',
        colorClass: 'zone-cold',
        bgClass: 'bg-blue-50 dark:bg-blue-950/20',
        textClass: 'text-blue-400',
        borderClass: 'bg-blue-300',
    },
};

/**
 * Classifica o nível da prateleira com base no índice relativo (topo=0).
 * indexFromTop: 0 = prateleira do topo, numShelves-1 = chão.
 * numShelves: total de prateleiras ativas na seção.
 */
export function getShelfLevel(indexFromTop: number, numShelves: number): ShelfLevel {
    if (numShelves === 1) return 'hand';
    if (numShelves === 2) return indexFromTop === 0 ? 'eye' : 'low';

    const ratio = indexFromTop / (numShelves - 1);

    if (ratio === 0) return 'high';
    if (ratio <= 0.35) return 'eye';
    if (ratio <= 0.70) return 'hand';
    return 'low';
}

export function getZoneConfig(level: ShelfLevel): ShelfZone {
    return ZONE_CONFIG[level];
}

export function useShelfZone(numShelves: Ref<number>) {
    const classifyShelf = (indexFromTop: number) =>
        getShelfLevel(indexFromTop, numShelves.value);

    const zoneConfig = (indexFromTop: number) =>
        getZoneConfig(classifyShelf(indexFromTop));

    return { classifyShelf, zoneConfig };
}

export const ZONE_LEGEND: ShelfZone[] = [
    ZONE_CONFIG.eye,
    ZONE_CONFIG.hand,
    ZONE_CONFIG.high,
    ZONE_CONFIG.low,
];
