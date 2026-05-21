/**
 * Core — estado global, persistência, histórico e seleção do planograma.
 *
 * Agrupa os composables fundamentais que sustentam o editor:
 *   - useGondolaState: singletons reativos (gôndola atual, drag state, filtros)
 *   - useLookupHelpers: busca por ID na árvore da gôndola
 *   - useReactivityHelpers: utilitários para forçar reatividade Vue
 *   - usePlanogramChanges: delta de mudanças + auto-save
 *   - usePlanogramHistory: undo/redo + localStorage
 *   - usePlanogramSelection: seleção única e múltipla de elementos
 *   - usePlanogramEditor: hub central do editor (orquestra os demais)
 */
export * from './useGondolaState';
export * from './useLookupHelpers';
export * from './useReactivityHelpers';
export * from './usePlanogramChanges';
export * from './usePlanogramHistory';
export * from './usePlanogramSelection';
export * from './usePlanogramEditor';
