/**
 * Composable para cálculos de furos da cremalheira
 *
 * Centraliza a lógica de cálculo de posições e dimensões dos furos
 * da cremalheira, evitando duplicação de código
 */

import type { Section } from '@/types/planogram';
import {
    DEFAULT_SECTION_FIELDS,
    calculateUsableHeight,
    toCamelCase,
} from './useSectionFields';

/**
 * Interface para um furo da cremalheira
 */
export interface Hole {
    width: number;
    height: number;
    spacing: number;
    position: number;
}

/**
 * Calcula as posições dos furos da cremalheira (apenas posições Y)
 * Retorna um array de números representando as posições verticais
 */
export function calculateHolePositions(section: Partial<Section>): number[] {
    const sectionCamel = toCamelCase(section);

    const height = sectionCamel.height ?? DEFAULT_SECTION_FIELDS.height;
    const baseHeight =
        sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;
    const holeHeight =
        sectionCamel.holeHeight ?? DEFAULT_SECTION_FIELDS.holeHeight;
    const holeSpacing =
        sectionCamel.holeSpacing ?? DEFAULT_SECTION_FIELDS.holeSpacing;

    const availableHeight = calculateUsableHeight(height, baseHeight);
    const totalSpaceNeeded = holeHeight + holeSpacing;
    const holeCount = Math.floor(availableHeight / totalSpaceNeeded);
    const remainingSpace =
        availableHeight -
        holeCount * holeHeight -
        (holeCount - 1) * holeSpacing;
    const marginTop = remainingSpace / 2;

    const positions: number[] = [];

    for (let i = 0; i < holeCount; i++) {
        const holePosition = marginTop + i * (holeHeight + holeSpacing);
        positions.push(holePosition);
    }

    return positions;
}

/**
 * Calcula os furos da cremalheira com todas as informações
 * Retorna um array de objetos Hole com width, height, spacing e position
 */
export function calculateHoles(section: Partial<Section>): Hole[] {
    const sectionCamel = toCamelCase(section);

    const height = sectionCamel.height ?? DEFAULT_SECTION_FIELDS.height;
    const baseHeight =
        sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;
    const holeHeight =
        sectionCamel.holeHeight ?? DEFAULT_SECTION_FIELDS.holeHeight;
    const holeWidth =
        sectionCamel.holeWidth ?? DEFAULT_SECTION_FIELDS.holeWidth;
    const holeSpacing =
        sectionCamel.holeSpacing ?? DEFAULT_SECTION_FIELDS.holeSpacing;

    const availableHeight = calculateUsableHeight(height, baseHeight);
    const totalSpaceNeeded = holeHeight + holeSpacing;
    const holeCount = Math.floor(availableHeight / totalSpaceNeeded);
    const remainingSpace =
        availableHeight -
        holeCount * holeHeight -
        (holeCount - 1) * holeSpacing;
    const marginTop = remainingSpace / 2;

    return Array.from({ length: holeCount }, (_, i) => ({
        width: holeWidth,
        height: holeHeight,
        spacing: holeSpacing,
        position: marginTop + i * (holeHeight + holeSpacing),
    }));
}

/**
 * Calcula a quantidade de furos que cabem na seção
 */
export function calculateHoleCount(section: Partial<Section>): number {
    const sectionCamel = toCamelCase(section);

    const height = sectionCamel.height ?? DEFAULT_SECTION_FIELDS.height;
    const baseHeight =
        sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;
    const holeHeight =
        sectionCamel.holeHeight ?? DEFAULT_SECTION_FIELDS.holeHeight;
    const holeSpacing =
        sectionCamel.holeSpacing ?? DEFAULT_SECTION_FIELDS.holeSpacing;

    const availableHeight = calculateUsableHeight(height, baseHeight);
    const totalSpaceNeeded = holeHeight + holeSpacing;

    return Math.floor(availableHeight / totalSpaceNeeded);
}

/**
 * Encontra o furo mais próximo de uma posição dada
 * Útil para snapping de prateleiras aos furos
 */
export function findNearestHole(
    section: Partial<Section>,
    position: number,
): number {
    const holePositions = calculateHolePositions(section);

    if (holePositions.length === 0) {
return position;
}

    // Encontra o furo mais próximo
    return holePositions.reduce((nearest, current) => {
        const distanceToCurrent = Math.abs(current - position);
        const distanceToNearest = Math.abs(nearest - position);

        return distanceToCurrent < distanceToNearest ? current : nearest;
    });
}

/**
 * Composable principal para cálculos de furos
 *
 * @param section - Seção para calcular os furos
 * @returns Objeto com funções e valores calculados
 */
export function useSectionHoles(section: Partial<Section>) {
    const holes = calculateHoles(section);
    const holePositions = calculateHolePositions(section);
    const holeCount = calculateHoleCount(section);

    return {
        holes,
        holePositions,
        holeCount,
        calculateHoles: () => calculateHoles(section),
        calculateHolePositions: () => calculateHolePositions(section),
        calculateHoleCount: () => calculateHoleCount(section),
        findNearestHole: (position: number) =>
            findNearestHole(section, position),
    };
}
