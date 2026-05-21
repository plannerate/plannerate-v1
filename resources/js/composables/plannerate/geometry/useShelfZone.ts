export type ShelfLevel = 'eye' | 'hand' | 'low' | 'high';

export type ZoneConfig = {
    level: ShelfLevel;
    label: string;
    labelShort: string;
    bgClass: string;
    textClass: string;
    /** true = zona quente (eye/hand) */
    isHot: boolean;
};

/**
 * Converte indexFromTop (0=topo, numShelves-1=chão) para ShelfLevel.
 * Espelha a lógica de ShelfLevel::fromShelfPosition() do backend PHP.
 *
 * Thresholds relativos (position / (numShelves - 1)):
 *   ≤ 0.20 → high  (acima dos olhos)
 *   ≤ 0.50 → eye   (nível dos olhos — zona quente)
 *   ≤ 0.80 → hand  (nível das mãos — zona quente)
 *   >  0.80 → low  (chão — zona fria)
 */
export function getShelfLevel(indexFromTop: number, numShelves: number): ShelfLevel {
    if (numShelves <= 1) return 'eye';
    const relative = indexFromTop / Math.max(1, numShelves - 1);
    if (relative <= 0.2) return 'high';
    if (relative <= 0.5) return 'eye';
    if (relative <= 0.8) return 'hand';
    return 'low';
}

/** Retorna classes Tailwind e rótulos para exibir a zona de uma prateleira. */
export function getZoneConfig(level: ShelfLevel): ZoneConfig {
    const configs: Record<ShelfLevel, ZoneConfig> = {
        eye: {
            level,
            label: 'Zona quente — nível dos olhos',
            labelShort: 'Olhos',
            bgClass: 'bg-rose-50',
            textClass: 'text-rose-600',
            isHot: true,
        },
        hand: {
            level,
            label: 'Zona quente — nível das mãos',
            labelShort: 'Mãos',
            bgClass: 'bg-amber-50',
            textClass: 'text-amber-600',
            isHot: true,
        },
        high: {
            level,
            label: 'Zona fria — acima dos olhos',
            labelShort: 'Alto',
            bgClass: 'bg-blue-50',
            textClass: 'text-blue-500',
            isHot: false,
        },
        low: {
            level,
            label: 'Zona fria — chão',
            labelShort: 'Chão',
            bgClass: 'bg-slate-50',
            textClass: 'text-slate-400',
            isHot: false,
        },
    };
    return configs[level];
}
