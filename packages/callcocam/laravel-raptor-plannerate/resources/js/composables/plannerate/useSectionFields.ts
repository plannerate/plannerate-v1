/**
 * Composable para campos comuns de Seção/Módulo
 *
 * Agrupa campos, valores padrão e funções helper para trabalhar
 * com dados de seção/módulo em diferentes contextos (camelCase e snake_case)
 */

import type { Section } from '@/types/planogram';

// Tipos para campos de seção em camelCase (usado no frontend)
export interface SectionFieldsCamel {
    name?: string;
    height?: number;
    width?: number;
    baseHeight?: number;
    baseWidth?: number;
    baseDepth?: number;
    rackWidth?: number;
    holeHeight?: number;
    holeWidth?: number;
    holeSpacing?: number;
    numShelves?: number;
}

// Tipos para campos de seção em snake_case (usado no backend)
export interface SectionFieldsSnake {
    name?: string;
    height?: number;
    width?: number;
    base_height?: number;
    base_width?: number;
    base_depth?: number;
    cremalheira_width?: number | string;
    hole_height?: number;
    hole_width?: number;
    hole_spacing?: number;
    num_shelves?: number;
}

/**
 * Valores padrão para campos de seção
 */
export const DEFAULT_SECTION_FIELDS: Required<SectionFieldsCamel> = {
    name: '',
    height: 200,
    width: 100,
    baseHeight: 20,
    baseWidth: 100,
    baseDepth: 50,
    rackWidth: 4,
    holeHeight: 3,
    holeWidth: 2,
    holeSpacing: 2,
    numShelves: 4,
};

/**
 * Converte campos de camelCase para snake_case
 */
export function toSnakeCase(fields: SectionFieldsCamel): SectionFieldsSnake {
    return {
        name: fields.name,
        height: fields.height,
        width: fields.width,
        base_height: fields.baseHeight,
        base_width: fields.baseWidth,
        base_depth: fields.baseDepth,
        cremalheira_width: fields.rackWidth,
        hole_height: fields.holeHeight,
        hole_width: fields.holeWidth,
        hole_spacing: fields.holeSpacing,
        num_shelves: fields.numShelves,
    };
}

/**
 * Converte campos de snake_case para camelCase
 */
export function toCamelCase(
    fields: SectionFieldsSnake | Partial<Section>,
): SectionFieldsCamel {
    return {
        name: fields.name,
        height: fields.height,
        width: fields.width,
        baseHeight: fields.base_height,
        baseWidth: fields.base_width,
        baseDepth: fields.base_depth,
        rackWidth:
            typeof fields.cremalheira_width === 'number'
                ? fields.cremalheira_width
                : parseFloat(fields.cremalheira_width as string) ||
                  DEFAULT_SECTION_FIELDS.rackWidth,
        holeHeight: fields.hole_height,
        holeWidth: fields.hole_width,
        holeSpacing: fields.hole_spacing,
        numShelves: fields.num_shelves,
    };
}

/**
 * Obtém valores iniciais para formulário de seção
 * Pode receber uma seção existente, última seção da lista, ou usar valores padrão
 */
export function getInitialSectionFields(
    existingSection?: Partial<Section> | null,
    lastSection?: Partial<Section> | null,
    gondolaHeight?: number,
    sectionIndex?: number,
): SectionFieldsCamel {
    // Se tem seção existente, usa ela
    if (existingSection) {
        return {
            ...DEFAULT_SECTION_FIELDS,
            ...toCamelCase(existingSection),
        };
    }

    // Se tem última seção, usa valores dela como base
    if (lastSection) {
        const lastFields = toCamelCase(lastSection);

        return {
            ...DEFAULT_SECTION_FIELDS,
            ...lastFields,
            name: `Módulo ${(sectionIndex ?? 0) + 1}`,
            height:
                gondolaHeight ??
                lastFields.height ??
                DEFAULT_SECTION_FIELDS.height,
        };
    }

    // Valores padrão
    return {
        ...DEFAULT_SECTION_FIELDS,
        name: `Módulo ${(sectionIndex ?? 0) + 1}`,
        height: gondolaHeight ?? DEFAULT_SECTION_FIELDS.height,
    };
}

/**
 * Valida campos básicos de seção
 */
export function validateSectionFields(
    fields: Partial<SectionFieldsCamel>,
): boolean {
    return (
        (fields.height ?? 0) >= 1 &&
        (fields.width ?? 0) >= 1 &&
        (fields.baseHeight ?? 0) >= 1 &&
        (fields.baseWidth ?? 0) >= 1 &&
        (fields.baseDepth ?? 0) >= 1 &&
        (fields.rackWidth ?? 0) >= 1 &&
        (fields.holeHeight ?? 0) >= 1 &&
        (fields.holeWidth ?? 0) >= 0.1 &&
        (fields.holeSpacing ?? 0) >= 0.1
    );
}

/**
 * Calcula altura útil da seção (altura total - altura da base)
 */
export function calculateUsableHeight(
    height: number,
    baseHeight: number,
): number {
    return Math.max(0, height - baseHeight);
}

/**
 * Composable principal para campos de seção
 *
 * @param initialData - Dados iniciais (opcional)
 * @param lastSection - Última seção da lista (para herdar valores)
 * @param gondolaHeight - Altura da gôndola (para inicializar altura)
 * @param sectionIndex - Índice da seção (para gerar nome)
 * @returns Objeto com campos, valores padrão e funções helper
 */
export function useSectionFields(
    initialData?: Partial<Section> | null,
    lastSection?: Partial<Section> | null,
    gondolaHeight?: number,
    sectionIndex?: number,
) {
    const defaultFields = getInitialSectionFields(
        initialData,
        lastSection,
        gondolaHeight,
        sectionIndex,
    );

    return {
        // Valores padrão
        defaults: DEFAULT_SECTION_FIELDS,

        // Campos iniciais
        initialFields: defaultFields,

        // Funções helper
        toSnakeCase,
        toCamelCase,
        validate: validateSectionFields,
        calculateUsableHeight,
    };
}
