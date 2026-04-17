export type { CategoryProduct, CategoryNode, HierarchyLevelNames } from '@/types/mercadologico';

export const LEVEL_NAMES: Record<number, string> = {
    1: 'Segmento',
    2: 'Departamento',
    3: 'Subdepartamento',
    4: 'Categoria',
    5: 'Subcategoria',
    6: 'Segmento',
    7: 'Subsegmento',
    8: 'Atributo',
};

export const LEVEL_COLORS = [
    'rgb(91, 106, 245)',   // --l1
    'rgb(139, 92, 246)',   // --l2
    'rgb(6, 182, 212)',    // --l3
    'rgb(16, 185, 129)',   // --l4
    'rgb(245, 158, 11)',   // --l5
    'rgb(239, 68, 68)',    // --l6
    'rgb(236, 72, 153)',   // --l7
];

/** Converts `rgb(r,g,b)` to `rgba(r,g,b,a)` for highlight backgrounds. */
export function colorWithAlpha(rgb: string, alpha: number): string {
    const match = rgb.match(/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i);
    if (!match) {
        return rgb;
    }

    const [, r, g, b] = match;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export function flattenByDepth(nodes: CategoryNode[], depthMap: Map<number, CategoryNode[]> = new Map(), depth = 0): Map<number, CategoryNode[]> {
    for (const node of nodes) {
        const d = node.depth ?? depth;
        if (!depthMap.has(d)) depthMap.set(d, []);
        depthMap.get(d)!.push(node);
        if (node.children?.length) flattenByDepth(node.children, depthMap, d + 1);
    }
    return depthMap;
}

export function countChildren(node: CategoryNode): number {
    if (node.children && node.children.length > 0) {
        return node.children.length;
    }
    return node.children_count ?? 0;
}

export function totalDescendants(node: CategoryNode): number {
    let n = node.children?.length ?? 0;
    for (const c of node.children ?? []) n += totalDescendants(c);
    return n;
}

export function findNodeById(nodes: CategoryNode[], id: string): CategoryNode | null {
    for (const node of nodes) {
        if (node.id === id) return node;
        const found = findNodeById(node.children ?? [], id);
        if (found) return found;
    }
    return null;
}

export function getPathNames(nodes: CategoryNode[], targetId: string, path: string[] = []): string[] | null {
    for (const node of nodes) {
        const p = [...path, node.name];
        if (node.id === targetId) return p;
        const found = getPathNames(node.children ?? [], targetId, p);
        if (found) return found;
    }
    return null;
}

/** Retorna os IDs de todos os descendentes do nó (filhos, netos, etc.). */
export function getDescendantIds(node: CategoryNode): string[] {
    const ids: string[] = [];
    for (const child of node.children ?? []) {
        ids.push(child.id);
        ids.push(...getDescendantIds(child));
    }
    return ids;
}

/** Retorna os IDs dos ancestrais do nó (pais até a raiz), do mais próximo ao mais distante. */
export function getAncestorIds(nodes: CategoryNode[], targetId: string, ancestors: string[] = []): string[] | null {
    for (const node of nodes) {
        if (node.id === targetId) return ancestors;
        const found = getAncestorIds(node.children ?? [], targetId, [...ancestors, node.id]);
        if (found) return found;
    }
    return null;
}

/** Returns the root node id for a given node id (top-level ancestor). */
export function getRootId(nodes: CategoryNode[], targetId: string): string | null {
    const ancestors = getAncestorIds(nodes, targetId);
    if (ancestors === null) {
        return null;
    }

    if (ancestors.length === 0) {
        return targetId;
    }

    return ancestors[0] ?? null;
}
