import type { MercadologicoUrls } from '@/components/mercadologico/types';

/**
 * Helpers de URL locais para as rotas do mercadológico no **landlord**.
 *
 * Escritos à mão de propósito: o `CategoryTreeController` não é rastreado pelo
 * gerador do Wayfinder (que, além disso, apagaria um arquivo de actions manual a
 * cada regeneração). Como a tela vive no host do landlord, paths relativos
 * resolvem no host/porta corrente do navegador.
 */

const base = (tenantId: string): string => `/tenants/${tenantId}/mercadologico`;

const categories = (tenantId: string): string => `${base(tenantId)}/categories`;

export const mercadologicoUrls = {
    index: (tenantId: string): string => base(tenantId),
    children: (tenantId: string): string => `${base(tenantId)}/children`,
    products: (tenantId: string, categoryId: string): string =>
        `${base(tenantId)}/${categoryId}/products`,
    move: (tenantId: string, categoryId: string): string =>
        `${base(tenantId)}/${categoryId}/move`,
    moveProducts: (tenantId: string): string => `${base(tenantId)}/move-products`,
    store: (tenantId: string): string => categories(tenantId),
    update: (tenantId: string, categoryId: string): string =>
        `${categories(tenantId)}/${categoryId}`,
    destroy: (tenantId: string, categoryId: string): string =>
        `${categories(tenantId)}/${categoryId}`,
    restore: (tenantId: string, categoryId: string): string =>
        `${categories(tenantId)}/${categoryId}/restore`,
};

/**
 * Constrói o resolvedor de URLs esperado pelo `MercadologicoManager` a partir do
 * id do tenant (contexto landlord).
 */
export function landlordMercadologicoUrls(tenantId: string): MercadologicoUrls {
    return {
        children: () => mercadologicoUrls.children(tenantId),
        products: (categoryId) => mercadologicoUrls.products(tenantId, categoryId),
        move: (categoryId) => mercadologicoUrls.move(tenantId, categoryId),
        moveProducts: () => mercadologicoUrls.moveProducts(tenantId),
        store: () => mercadologicoUrls.store(tenantId),
        update: (categoryId) => mercadologicoUrls.update(tenantId, categoryId),
        destroy: (categoryId) => mercadologicoUrls.destroy(tenantId, categoryId),
        restore: (categoryId) => mercadologicoUrls.restore(tenantId, categoryId),
    };
}
