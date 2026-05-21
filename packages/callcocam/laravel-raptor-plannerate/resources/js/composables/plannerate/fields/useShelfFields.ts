/**
 * Composable para campos comuns de Prateleira
 *
 * Agrupa campos, valores padrão e funções helper para trabalhar
 * com dados de prateleira em diferentes contextos (camelCase e snake_case)
 */

import type { Shelf } from '@/types/planogram';

// Tipos para campos de prateleira em camelCase (usado no frontend)
export interface ShelfFieldsCamel {
    shelfHeight?: number;
    height?: number;
    shelfWidth?: number;
    width?: number;
    shelfDepth?: number;
    depth?: number;
    productType?: 'normal' | 'hook';
    numShelves?: number;
}

// Tipos para campos de prateleira em snake_case (usado no backend)
export interface ShelfFieldsSnake {
    shelf_height?: number;
    height?: number;
    shelf_width?: number;
    width?: number;
    shelf_depth?: number;
    depth?: number;
    product_type?: 'normal' | 'hook';
    num_shelves?: number;
}

/**
 * Valores padrão para campos de prateleira
 */
export const DEFAULT_SHELF_FIELDS: Required<ShelfFieldsCamel> = {
    shelfHeight: 4,
    height: 4,
    shelfWidth: 100,
    width: 100,
    shelfDepth: 40,
    depth: 40,
    productType: 'normal',
    numShelves: 4,
};

/**
 * Converte campos de camelCase para snake_case
 */
export function toSnakeCase(fields: ShelfFieldsCamel): ShelfFieldsSnake {
    return {
        shelf_height: fields.shelfHeight ?? fields.height,
        height: fields.shelfHeight ?? fields.height,
        shelf_width: fields.shelfWidth ?? fields.width,
        width: fields.shelfWidth ?? fields.width,
        shelf_depth: fields.shelfDepth ?? fields.depth,
        depth: fields.shelfDepth ?? fields.depth,
        product_type: fields.productType,
        num_shelves: fields.numShelves,
    };
}

/**
 * Converte campos de snake_case para camelCase
 */
export function toCamelCase(
    fields: ShelfFieldsSnake | Partial<Shelf>,
): ShelfFieldsCamel {
    // Shelf usa shelf_height, shelf_width, shelf_depth (não height, width, depth)
    // ShelfFieldsSnake pode ter shelf_height ou height
    const shelf = fields as Partial<Shelf>;
    const snake = fields as ShelfFieldsSnake;

    const shelfHeight =
        shelf.shelf_height ?? snake.shelf_height ?? snake.height;
    const shelfWidth = shelf.shelf_width ?? snake.shelf_width ?? snake.width;
    const shelfDepth = shelf.shelf_depth ?? snake.shelf_depth ?? snake.depth;

    return {
        shelfHeight: shelfHeight ?? 0,
        height: shelfHeight ?? 0,
        shelfWidth: shelfWidth ?? 0,
        width: shelfWidth ?? 0,
        shelfDepth: shelfDepth ?? 0,
        depth: shelfDepth ?? 0,
        productType: (shelf.product_type ?? snake.product_type) as
            | 'normal'
            | 'hook'
            | undefined,
        numShelves: snake.num_shelves,
    };
}

/**
 * Obtém valores iniciais para formulário de prateleira
 * Pode receber uma prateleira existente, última prateleira da lista, ou usar valores padrão
 */
export function getInitialShelfFields(
    existingShelf?: Partial<Shelf> | null,
    lastShelf?: Partial<Shelf> | null,
): ShelfFieldsCamel {
    // Se tem prateleira existente, usa ela
    if (existingShelf) {
        return {
            ...DEFAULT_SHELF_FIELDS,
            ...toCamelCase(existingShelf),
        };
    }

    // Se tem última prateleira, usa valores dela como base
    if (lastShelf) {
        return {
            ...DEFAULT_SHELF_FIELDS,
            ...toCamelCase(lastShelf),
        };
    }

    // Valores padrão
    return {
        ...DEFAULT_SHELF_FIELDS,
    };
}

/**
 * Valida campos básicos de prateleira
 */
export function validateShelfFields(
    fields: Partial<ShelfFieldsCamel>,
): boolean {
    const height = fields.shelfHeight ?? fields.height ?? 0;
    const width = fields.shelfWidth ?? fields.width ?? 0;
    const depth = fields.shelfDepth ?? fields.depth ?? 0;
    const numShelves = fields.numShelves ?? 0;

    return (
        height >= 1 &&
        width >= 1 &&
        depth >= 1 &&
        numShelves >= 0 &&
        (fields.productType === 'normal' || fields.productType === 'hook')
    );
}

/**
 * Calcula espaçamento médio entre prateleiras
 */
export function calculateShelfSpacing(
    usableHeight: number,
    shelfHeight: number,
    numShelves: number,
): number {
    if (numShelves === 0) {
return 0;
}

    if (numShelves === 1) {
return Math.max(0, usableHeight);
}

    const totalShelfHeight = numShelves * shelfHeight;
    const remainingHeight = usableHeight - totalShelfHeight;

    if (remainingHeight <= 0) {
return 0;
}

    return remainingHeight / (numShelves - 1);
}

/**
 * Calcula área total de exposição (área de todas as prateleiras)
 */
export function calculateTotalDisplayArea(
    shelfWidth: number,
    shelfDepth: number,
    numShelves: number,
    numModules: number = 1,
): number {
    const areaPerShelf = shelfWidth * shelfDepth;

    return numModules * numShelves * areaPerShelf;
}

/**
 * Composable principal para campos de prateleira
 *
 * @param initialData - Dados iniciais (opcional)
 * @param lastShelf - Última prateleira da lista (para herdar valores)
 * @returns Objeto com campos, valores padrão e funções helper
 */
export function useShelfFields(
    initialData?: Partial<Shelf> | null,
    lastShelf?: Partial<Shelf> | null,
) {
    const defaultFields = getInitialShelfFields(initialData, lastShelf);

    return {
        // Valores padrão
        defaults: DEFAULT_SHELF_FIELDS,

        // Campos iniciais
        initialFields: defaultFields,

        // Funções helper
        toSnakeCase,
        toCamelCase,
        validate: validateShelfFields,
        calculateShelfSpacing,
        calculateTotalDisplayArea,
    };
}
