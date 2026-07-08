import type { MercadologicoUrls } from '@/components/mercadologico/types';

/**
 * Helpers de URL locais para as rotas do mercadológico no **tenant**.
 *
 * Escritos à mão de propósito: o `MercadologicoController` do tenant não é
 * rastreado pelo gerador do Wayfinder (que, além disso, apagaria um arquivo de
 * actions manual a cada regeneração). Como a tela vive no próprio host do tenant,
 * paths relativos resolvem no host/porta corrente do navegador — não é preciso
 * passar o id do tenant (ele é resolvido pelo host).
 */

const base = '/mercadologico';

const categories = `${base}/categories`;

export const mercadologicoUrls = {
    index: (): string => base,
    children: (): string => `${base}/children`,
    products: (categoryId: string): string => `${base}/${categoryId}/products`,
    move: (categoryId: string): string => `${base}/${categoryId}/move`,
    moveProducts: (): string => `${base}/move-products`,
    store: (): string => categories,
    update: (categoryId: string): string => `${categories}/${categoryId}`,
    destroy: (categoryId: string): string => `${categories}/${categoryId}`,
    restore: (categoryId: string): string => `${categories}/${categoryId}/restore`,
};

/**
 * Constrói o resolvedor de URLs esperado pelo `MercadologicoManager` para o
 * contexto do tenant ativo.
 */
export function tenantMercadologicoUrls(): MercadologicoUrls {
    return {
        children: () => mercadologicoUrls.children(),
        products: (categoryId) => mercadologicoUrls.products(categoryId),
        move: (categoryId) => mercadologicoUrls.move(categoryId),
        moveProducts: () => mercadologicoUrls.moveProducts(),
        store: () => mercadologicoUrls.store(),
        update: (categoryId) => mercadologicoUrls.update(categoryId),
        destroy: (categoryId) => mercadologicoUrls.destroy(categoryId),
        restore: (categoryId) => mercadologicoUrls.restore(categoryId),
    };
}
