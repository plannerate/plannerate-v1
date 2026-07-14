import { computed  } from 'vue';
import type {Ref} from 'vue';
import type { Section } from '@/types/planogram';

/**
 * Opção genérica de select (valor + rótulo legível).
 */
export interface StructureOption {
    value: string;
    label: string;
}

/**
 * Produto da gôndola exposto para a busca da divergência.
 */
export interface StructureProduct {
    id: string;
    name: string;
    ean: string | null;
    codigo_erp: string | null;
}

/**
 * Deriva a estrutura física da gôndola (módulos, prateleiras, posições e
 * produtos) a partir das `sections` que a tela de print já recebe — alimentando
 * os selects e a busca dos modais de evidência/divergência sem novas queries.
 */
export function useExecutionStructure(sections: Ref<Section[] | undefined>) {
    /** Módulos = seções, rotuladas "Módulo - N" pela ordem física. */
    const modules = computed<StructureOption[]>(() =>
        [...(sections.value ?? [])]
            .sort((a, b) => (a.ordering ?? 0) - (b.ordering ?? 0))
            .map((section, index) => ({
                value: section.id,
                label: `Módulo - ${index + 1}`,
            })),
    );

    /** Prateleiras do módulo informado, rotuladas "Prateleira - N". */
    function shelvesFor(moduleId: string | null): StructureOption[] {
        if (!moduleId) {
            return [];
        }

        const section = (sections.value ?? []).find((item) => item.id === moduleId);

        return [...(section?.shelves ?? [])]
            .sort((a, b) => (a.ordering ?? 0) - (b.ordering ?? 0))
            .map((shelf, index) => ({
                value: shelf.id,
                label: `Prateleira - ${index + 1}`,
            }));
    }

    /** Posições (facings) da prateleira informada, rotuladas "Posição - N". */
    function positionsFor(moduleId: string | null, shelfId: string | null): StructureOption[] {
        if (!moduleId || !shelfId) {
            return [];
        }

        const section = (sections.value ?? []).find((item) => item.id === moduleId);
        const shelf = section?.shelves?.find((item) => item.id === shelfId);

        return [...(shelf?.segments ?? [])]
            .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
            .map((segment, index) => ({
                value: String(segment.position ?? index + 1),
                label: `Posição - ${index + 1}`,
            }));
    }

    /** Lista única de produtos da gôndola (para a busca da divergência). */
    const products = computed<StructureProduct[]>(() => {
        const byId = new Map<string, StructureProduct>();

        for (const section of sections.value ?? []) {
            for (const shelf of section.shelves ?? []) {
                for (const segment of shelf.segments ?? []) {
                    const product = segment.layer?.product;

                    if (product?.id && !byId.has(product.id)) {
                        byId.set(product.id, {
                            id: product.id,
                            name: product.name ?? '—',
                            ean: product.ean ?? null,
                            codigo_erp: product.codigo_erp ?? null,
                        });
                    }
                }
            }
        }

        return [...byId.values()];
    });

    /** Resolve o nome de exibição de um produto pelo id. */
    function productName(productId: string | null): string | null {
        if (!productId) {
            return null;
        }

        return products.value.find((product) => product.id === productId)?.name ?? productId;
    }

    return { modules, shelvesFor, positionsFor, products, productName };
}
