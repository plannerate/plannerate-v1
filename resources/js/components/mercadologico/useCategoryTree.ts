import { useHttp } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import type { MercadologicoUrls, TreeNode } from './types';

/**
 * Estado em runtime de um nó da árvore.
 */
export type NodeState = {
    node: TreeNode;
    /** Pai do nó na árvore; `null` para raízes. */
    parentId: string | null;
    /** IDs dos filhos já carregados; `null` = ainda não carregados. */
    childrenIds: string[] | null;
    expanded: boolean;
    loading: boolean;
};

/**
 * Store da árvore de categorias com carregamento preguiçoso (lazy).
 *
 * Mantém os nós normalizados por id, expõe as raízes e carrega os filhos sob
 * demanda (endpoint `children`). Após um move, apenas os ramos afetados são
 * recarregados — preservando o estado de expansão do restante da árvore.
 */
export function useCategoryTree(urls: MercadologicoUrls) {
    const nodes = reactive<Record<string, NodeState>>({});
    const rootIds = ref<string[]>([]);

    const http = useHttp<Record<string, string>, { nodes: TreeNode[] }>();

    /**
     * Registra/atualiza um nó preservando seu estado de expansão quando já existe.
     */
    function registerNode(node: TreeNode, parentId: string | null): void {
        const existing = nodes[node.id];

        if (existing) {
            existing.node = node;
            existing.parentId = parentId;

            return;
        }

        nodes[node.id] = {
            node,
            parentId,
            childrenIds: null,
            expanded: false,
            loading: false,
        };
    }

    /**
     * Semeia as raízes a partir das props do Inertia (só na primeira carga).
     */
    function seedRoots(roots: TreeNode[]): void {
        roots.forEach((node) => registerNode(node, null));
        rootIds.value = roots.map((node) => node.id);
    }

    /**
     * Busca os filhos diretos de um pai (ou as raízes quando `null`).
     */
    async function fetchChildren(parentId: string | null): Promise<TreeNode[]> {
        const url = new URL(urls.children(), window.location.origin);

        if (parentId) {
            url.searchParams.set('parent_id', parentId);
        }

        await http.get(url.toString());

        return Array.isArray(http.response?.nodes) ? http.response!.nodes : [];
    }

    /**
     * Garante que os filhos de um nó estejam carregados (carrega uma única vez).
     */
    async function ensureLoaded(id: string): Promise<void> {
        const state = nodes[id];

        if (!state || state.childrenIds !== null || state.loading) {
            return;
        }

        state.loading = true;

        try {
            const children = await fetchChildren(id);
            children.forEach((child) => registerNode(child, id));
            state.childrenIds = children.map((child) => child.id);
        } finally {
            state.loading = false;
        }
    }

    /**
     * Expande/recolhe um nó, carregando os filhos preguiçosamente na 1ª abertura.
     */
    async function toggle(id: string): Promise<void> {
        const state = nodes[id];

        if (!state) {
            return;
        }

        state.expanded = !state.expanded;

        if (state.expanded) {
            await ensureLoaded(id);
        }
    }

    /**
     * Recarrega os filhos de um nó (ou as raízes) — usado após um move para
     * refletir a saída/entrada de um nó sem recolher o resto da árvore.
     */
    async function refresh(parentId: string | null): Promise<void> {
        const children = await fetchChildren(parentId);
        children.forEach((child) => registerNode(child, parentId));
        const ids = children.map((child) => child.id);

        if (parentId === null) {
            rootIds.value = ids;

            return;
        }

        const state = nodes[parentId];

        if (state) {
            state.childrenIds = ids;
        }
    }

    /**
     * Ajusta a contagem de produtos de um nó (feedback imediato ao mover produtos).
     */
    function adjustProductsCount(categoryId: string, delta: number): void {
        const state = nodes[categoryId];

        if (state) {
            state.node.products_count = Math.max(
                0,
                state.node.products_count + delta,
            );
        }
    }

    function getNode(id: string): NodeState | undefined {
        return nodes[id];
    }

    /**
     * Estados dos filhos de um nó, na ordem carregada.
     */
    function childrenStates(id: string): NodeState[] {
        const state = nodes[id];

        if (!state?.childrenIds) {
            return [];
        }

        return state.childrenIds
            .map((childId) => nodes[childId])
            .filter((child): child is NodeState => Boolean(child));
    }

    /**
     * Verdadeiro se `maybeDescendantId` é o próprio nó ou um descendente já
     * carregado de `ancestorId` — usado como guard client-side do drag (o backend
     * mantém o guard definitivo, inclusive para ramos ainda não carregados).
     */
    function isSelfOrLoadedDescendant(
        ancestorId: string,
        maybeDescendantId: string,
    ): boolean {
        if (ancestorId === maybeDescendantId) {
            return true;
        }

        const state = nodes[ancestorId];

        if (!state?.childrenIds) {
            return false;
        }

        return state.childrenIds.some((childId) =>
            isSelfOrLoadedDescendant(childId, maybeDescendantId),
        );
    }

    return {
        nodes,
        rootIds,
        seedRoots,
        ensureLoaded,
        toggle,
        refresh,
        adjustProductsCount,
        getNode,
        childrenStates,
        isSelfOrLoadedDescendant,
    };
}

export type CategoryTreeStore = ReturnType<typeof useCategoryTree>;
