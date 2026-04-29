/**
 * Composable para campos comuns de Gôndola
 *
 * Agrupa campos, valores padrão e funções helper para trabalhar
 * com dados de gôndola em diferentes contextos (camelCase e snake_case)
 */

import type { Gondola } from '@/types/planogram';

// Tipos para campos de gôndola em camelCase (usado no frontend)
export interface GondolaFieldsCamel {
    gondolaName?: string;
    name?: string;
    location?: string;
    side?: string;
    scaleFactor?: number;
    flow?: 'left_to_right' | 'right_to_left';
    status?: 'draft' | 'published';
    alignment?: 'left' | 'right' | 'center' | 'justify';
    numModules?: number;
}

// Tipos para campos de gôndola em snake_case (usado no backend)
export interface GondolaFieldsSnake {
    name?: string;
    location?: string;
    side?: string;
    scale_factor?: number;
    flow?: 'left_to_right' | 'right_to_left';
    status?: 'draft' | 'published';
    alignment?: 'left' | 'right' | 'center' | 'justify';
    num_modulos?: number;
}

/**
 * Valores padrão para campos de gôndola
 */
export const DEFAULT_GONDOLA_FIELDS: Required<GondolaFieldsCamel> = {
    gondolaName: '',
    name: '',
    location: 'Corredor 1',
    side: 'A',
    scaleFactor: 3,
    flow: 'left_to_right',
    status: 'draft',
    alignment: 'left',
    numModules: 4,
};

/**
 * Gera código único para gôndola
 */
export function generateGondolaCode(): string {
    const prefix = 'GND';
    const date = new Date();
    const year = date.getFullYear().toString().slice(2);
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const random = Math.floor(Math.random() * 10000)
        .toString()
        .padStart(4, '0');

    return `${prefix}-${year}${month}-${random}`;
}

/**
 * Converte campos de camelCase para snake_case
 */
export function toSnakeCase(fields: GondolaFieldsCamel): GondolaFieldsSnake {
    return {
        name: fields.gondolaName || fields.name,
        location: fields.location,
        side: fields.side,
        scale_factor: fields.scaleFactor,
        flow: fields.flow,
        status: fields.status,
        alignment: fields.alignment,
        num_modulos: fields.numModules,
    };
}

/**
 * Converte campos de snake_case para camelCase
 */
export function toCamelCase(
    fields: GondolaFieldsSnake | Partial<Gondola>,
): GondolaFieldsCamel {
    return {
        gondolaName: fields.name,
        name: fields.name,
        location: fields.location,
        side: fields.side,
        scaleFactor: fields.scale_factor,
        flow: fields.flow as 'left_to_right' | 'right_to_left' | undefined,
        status: fields.status as 'draft' | 'published' | undefined,
        alignment: fields.alignment,
        numModules: fields.num_modulos,
    };
}

/**
 * Obtém valores iniciais para formulário de gôndola
 * Pode receber uma gôndola existente ou usar valores padrão
 */
export function getInitialGondolaFields(
    existingGondola?: Partial<Gondola> | null,
    gondolaSettings?: any,
): GondolaFieldsCamel {
    if (existingGondola) {
        return {
            ...DEFAULT_GONDOLA_FIELDS,
            ...toCamelCase(existingGondola),
            gondolaName: existingGondola.name || generateGondolaCode(),
        };
    } 

    if (gondolaSettings) {
        return {
            ...DEFAULT_GONDOLA_FIELDS,
            ...toCamelCase(gondolaSettings),
            gondolaName: generateGondolaCode(),
        };
    }

    return {
        ...DEFAULT_GONDOLA_FIELDS,
        gondolaName: generateGondolaCode(),
    };
}

/**
 * Valida campos básicos de gôndola
 */
export function validateGondolaFields(
    fields: Partial<GondolaFieldsCamel>,
): boolean {
    const name = fields.gondolaName || fields.name;

    return (
        !!name?.trim() &&
        !!fields.side?.trim() &&
        (fields.scaleFactor ?? 0) >= 1 &&
        (fields.flow === 'left_to_right' || fields.flow === 'right_to_left')
    );
}

/**
 * Composable principal para campos de gôndola
 *
 * @param initialData - Dados iniciais (opcional)
 * @returns Objeto com campos, valores padrão e funções helper
 */
export function useGondolaFields(initialData?: Partial<Gondola> | null) {
    const defaultFields = getInitialGondolaFields(initialData);

    return {
        // Valores padrão
        defaults: DEFAULT_GONDOLA_FIELDS,

        // Campos iniciais
        initialFields: defaultFields,

        // Funções helper
        generateCode: generateGondolaCode,
        toSnakeCase,
        toCamelCase,
        validate: validateGondolaFields,
    };
}
