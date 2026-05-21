/**
 * Operations — mutações de baixo nível na estrutura da gôndola.
 *
 * Agrupa as operações de CRUD sobre seções, prateleiras e segmentos,
 * além dos tipos e gerenciador de snapshots para undo/redo:
 *   - useSectionOperations: adicionar/remover seções
 *   - useShelfOperations: adicionar/remover/reordenar prateleiras
 *   - useSegmentOperations: mover/copiar/trocar segmentos entre prateleiras
 *   - useSnapshotManager: capturar/aplicar snapshots de estado
 *   - useSnapshotTypes: tipos de estado para cada operação de snapshot
 */
export * from './useSectionOperations';
export * from './useShelfOperations';
export * from './useSegmentOperations';
export * from './useSnapshotManager';
export * from './useSnapshotTypes';
