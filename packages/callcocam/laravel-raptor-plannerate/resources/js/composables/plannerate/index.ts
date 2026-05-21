/**
 * Barrel raiz — reexporta todos os composables do módulo plannerate.
 *
 * Grupos lógicos disponíveis (importação direta por grupo):
 *   @/composables/plannerate/core         — estado, histórico, seleção, editor
 *   @/composables/plannerate/operations   — mutações de baixo nível (section/shelf/segment)
 *   @/composables/plannerate/actions      — comandos UI+teclado por tipo de elemento
 *   @/composables/plannerate/interactions — teclado, drag & drop, produtos rejeitados
 *   @/composables/plannerate/fields       — campos camel/snake, defaults, validação
 *   @/composables/plannerate/geometry     — cálculos visuais e zonas de prateleira
 *   @/composables/plannerate/analysis     — ABC, estoque-alvo, filtros
 *   @/composables/plannerate/products     — painel, vendas, imagem de produto
 *   @/composables/plannerate/export       — PDF e captura de canvas
 *   @/composables/plannerate/shared       — utilitários genéricos
 *
 * Atenção: toSnakeCase e toCamelCase existem nos três módulos de fields com
 * assinaturas distintas — importar diretamente do arquivo correto:
 *   import { toSnakeCase } from '@/composables/plannerate/useSectionFields'
 */
export * from './core';
export * from './operations';
export * from './actions';
export * from './interactions';
export * from './fields';
export * from './geometry';
export * from './analysis';
export * from './products';
export * from './export';
export * from './shared';
