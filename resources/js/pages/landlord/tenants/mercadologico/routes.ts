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

export const mercadologicoUrls = {
    index: (tenantId: string): string => base(tenantId),
    children: (tenantId: string): string => `${base(tenantId)}/children`,
    products: (tenantId: string, categoryId: string): string =>
        `${base(tenantId)}/${categoryId}/products`,
    move: (tenantId: string, categoryId: string): string =>
        `${base(tenantId)}/${categoryId}/move`,
    moveProducts: (tenantId: string): string => `${base(tenantId)}/move-products`,
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
    };
}
