import { useHttp } from '@inertiajs/vue3';

import type { MercadologicoUrls, TreeNode } from './types';

/** Campos do formulário de categoria. */
export type CategoryFormData = {
    name: string;
    codigo: number | null;
    status: string;
};

type CategoryResponse = { category: TreeNode };

/**
 * Extrai a primeira mensagem de erro de validação retornada pelo backend.
 */
function firstErrorMessage(errors: Record<string, unknown>): string {
    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }

        if (typeof value === 'string' && value !== '') {
            return value;
        }
    }

    return '';
}

/**
 * Operações CRUD de categoria contra os endpoints JSON do mercadológico.
 *
 * Cada método resolve com o nó atualizado (ou void) e, em caso de erro de
 * validação (ex.: excluir categoria não vazia), lança um `Error` com a mensagem
 * do backend para o chamador exibir num toast.
 */
export function useCategoryCrud(urls: MercadologicoUrls) {
    const createHttp = useHttp<
        { parent_id: string | null } & CategoryFormData,
        CategoryResponse
    >({ parent_id: null, name: '', codigo: null, status: 'draft' });

    const updateHttp = useHttp<CategoryFormData, CategoryResponse>({
        name: '',
        codigo: null,
        status: 'draft',
    });

    const deleteHttp = useHttp<Record<string, never>, { ok: boolean }>({});
    const restoreHttp = useHttp<Record<string, never>, CategoryResponse>({});

    async function create(
        parentId: string | null,
        data: CategoryFormData,
    ): Promise<TreeNode> {
        createHttp.clearErrors();
        createHttp.parent_id = parentId;
        createHttp.name = data.name;
        createHttp.codigo = data.codigo;
        createHttp.status = data.status;

        try {
            const res = await createHttp.post(urls.store());

            if (res?.category) {
                return res.category;
            }
        } catch {
            // cai na extração de erro abaixo
        }

        throw new Error(firstErrorMessage(createHttp.errors));
    }

    async function update(
        categoryId: string,
        data: CategoryFormData,
    ): Promise<TreeNode> {
        updateHttp.clearErrors();
        updateHttp.name = data.name;
        updateHttp.codigo = data.codigo;
        updateHttp.status = data.status;

        try {
            const res = await updateHttp.put(urls.update(categoryId));

            if (res?.category) {
                return res.category;
            }
        } catch {
            // cai na extração de erro abaixo
        }

        throw new Error(firstErrorMessage(updateHttp.errors));
    }

    async function remove(categoryId: string): Promise<void> {
        deleteHttp.clearErrors();

        try {
            const res = await deleteHttp.delete(urls.destroy(categoryId));

            if (res?.ok) {
                return;
            }
        } catch {
            // cai na extração de erro abaixo
        }

        throw new Error(firstErrorMessage(deleteHttp.errors));
    }

    async function restore(categoryId: string): Promise<TreeNode> {
        restoreHttp.clearErrors();

        try {
            const res = await restoreHttp.post(urls.restore(categoryId));

            if (res?.category) {
                return res.category;
            }
        } catch {
            // cai na extração de erro abaixo
        }

        throw new Error(firstErrorMessage(restoreHttp.errors));
    }

    return { create, update, remove, restore };
}

export type CategoryCrud = ReturnType<typeof useCategoryCrud>;
