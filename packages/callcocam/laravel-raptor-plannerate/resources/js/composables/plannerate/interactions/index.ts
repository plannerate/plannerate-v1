/**
 * Interactions — teclado, drag & drop e módulo de produtos rejeitados.
 *
 * Agrupa a camada de interação do editor, separando responsabilidades de
 * entrada (teclado/mouse) da lógica de negócio (operations/actions):
 *   - usePlanogramKeyboard: atalhos de teclado (Ctrl+Z, setas, Delete, etc.)
 *   - useShelfDrag: estado de drag de prateleiras entre seções
 *   - useShelfDragDrop: handlers de drag & drop por prateleira
 *   - useRejectedProductsStore: callback de produto posicionado (cleared on use)
 *   - useRejectedProductsModule: painel de produtos rejeitados + modo troca
 */
export * from './usePlanogramKeyboard';
export * from './useShelfDrag';
export * from './useShelfDragDrop';
export * from './useRejectedProductsStore';
export * from './useRejectedProductsModule';
