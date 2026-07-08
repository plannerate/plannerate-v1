/**
 * Tipos compartilhados dos componentes de manutenção do mercadológico.
 *
 * São agnósticos de contexto (landlord ou tenant): as URLs de backend são
 * injetadas via {@link MercadologicoUrls}, permitindo reusar os componentes em
 * qualquer página que forneça os endpoints equivalentes.
 */

/** Nó da árvore de categorias (payload de `nodesForParent` no backend). */
export type TreeNode = {
    id: string;
    name: string;
    level_name: string | null;
    nivel: string | null;
    codigo: number | null;
    status: string;
    is_placeholder: boolean;
    children_count: number;
    products_count: number;
};

/** Produto exibido na modal de uma categoria. */
export type ProductRow = {
    id: string;
    name: string;
    ean: string | null;
    codigo_erp: string | null;
    image_url: string;
};

/** Categoria aberta como modal (uma por categoria). */
export type OpenModal = {
    categoryId: string;
    categoryName: string;
};

/**
 * Resolvedores de URL dos endpoints do mercadológico.
 *
 * Cada página (landlord ou tenant) constrói este objeto com seus próprios paths,
 * mantendo os componentes desacoplados do roteamento concreto.
 */
export interface MercadologicoUrls {
    /** Filhos diretos de um pai (o `parent_id` é anexado como query pelo store). */
    children: () => string;
    /** Produtos de uma categoria (paginado/buscável). */
    products: (categoryId: string) => string;
    /** Reparent de uma categoria. */
    move: (categoryId: string) => string;
    /** Movimentação de produtos entre categorias. */
    moveProducts: () => string;
    /** Cria uma categoria (raiz ou subcategoria). */
    store: () => string;
    /** Edita uma categoria. */
    update: (categoryId: string) => string;
    /** Exclui (soft delete) uma categoria. */
    destroy: (categoryId: string) => string;
    /** Restaura uma categoria excluída. */
    restore: (categoryId: string) => string;
}
