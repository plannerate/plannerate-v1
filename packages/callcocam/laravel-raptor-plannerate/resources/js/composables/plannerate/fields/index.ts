/**
 * Fields — mapeamento camel/snake, defaults e validação de campos de formulário.
 *
 * Cada arquivo expõe um par de interfaces (CamelCase ↔ SnakeCase), constantes
 * de campos padrão, funções de conversão e validação. As funções genéricas
 * `toSnakeCase` e `toCamelCase` existem nos três arquivos com assinaturas
 * diferentes — importá-las diretamente do arquivo correto quando necessário:
 *   import { toSnakeCase } from '@/composables/plannerate/fields/useSectionFields'
 *
 * Este barrel exporta os símbolos não-conflitantes de cada módulo:
 *   - useSectionFields / DEFAULT_SECTION_FIELDS / getInitialSectionFields / validateSectionFields / calculateUsableHeight
 *   - useShelfFields / DEFAULT_SHELF_FIELDS / getInitialShelfFields / validateShelfFields / calculateShelfSpacing / calculateTotalDisplayArea
 *   - useGondolaFields / DEFAULT_GONDOLA_FIELDS / getInitialGondolaFields / validateGondolaFields / generateGondolaCode
 */

// --- Section ---
export type { SectionFieldsCamel, SectionFieldsSnake } from './useSectionFields';
export {
    DEFAULT_SECTION_FIELDS,
    getInitialSectionFields,
    validateSectionFields,
    calculateUsableHeight,
    useSectionFields,
} from './useSectionFields';

// --- Shelf ---
export type { ShelfFieldsCamel, ShelfFieldsSnake } from './useShelfFields';
export {
    DEFAULT_SHELF_FIELDS,
    getInitialShelfFields,
    validateShelfFields,
    calculateShelfSpacing,
    calculateTotalDisplayArea,
    useShelfFields,
} from './useShelfFields';

// --- Gondola ---
export type { GondolaFieldsCamel, GondolaFieldsSnake } from './useGondolaFields';
export {
    DEFAULT_GONDOLA_FIELDS,
    getInitialGondolaFields,
    validateGondolaFields,
    generateGondolaCode,
    useGondolaFields,
} from './useGondolaFields';
