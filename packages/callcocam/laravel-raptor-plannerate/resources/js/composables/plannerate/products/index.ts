/**
 * Products — painel de produtos, dados de vendas e download de imagens.
 *
 * Agrupa os composables que interagem com o catálogo de produtos:
 *   - useProductsPanel: listagem, paginação e filtros do painel lateral
 *   - useProductSales: fetch e estruturação de dados de vendas por EAN
 *   - useProductImage: download de imagem de produto por EAN
 */
export * from './useProductsPanel';
export * from './useProductSales';
export * from './useProductImage';
