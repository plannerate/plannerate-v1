/**
 * Actions — lógica de ação compartilhada entre UI e teclado.
 *
 * Cada composable expõe comandos de alto nível para manipular um tipo
 * de elemento (seção, prateleira, segmento), consumindo as Operations
 * de baixo nível e aplicando regras de negócio e feedback visual:
 *   - useSectionActions: comandos de seção (adicionar, remover, redimensionar)
 *   - useShelfActions: comandos de prateleira (mover, clonar, remover)
 *   - useSegmentActions: comandos de segmento (mover, copiar, ajustar facings)
 *
 * Nota: shouldShowDeleteConfirm é omitido intencionalmente — importar de
 * @/composables/plannerate/shared ou @/composables/plannerate/shared/usePlanogramUtils.
 */
export { useSectionActions } from './useSectionActions';
export { shelvesMovingBetweenSections, useShelfActions } from './useShelfActions';
export { segmentsMoving, useSegmentActions } from './useSegmentActions';
