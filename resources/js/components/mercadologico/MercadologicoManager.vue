<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FolderTree, Plus, Redo2, Undo2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';

import CategoryFormDialog from './CategoryFormDialog.vue';
import CategoryTree from './CategoryTree.vue';
import FloatingProductsPanel from './FloatingProductsPanel.vue';
import type { MercadologicoUrls, OpenModal, TreeNode } from './types';
import type { CategoryFormData } from './useCategoryCrud';
import { useCategoryCrud } from './useCategoryCrud';
import type { DropTarget } from './useCategoryDrag';
import { ROOT_TARGET } from './useCategoryDrag';
import { useCategoryDrag } from './useCategoryDrag';
import { useCategoryHistory } from './useCategoryHistory';
import { useCategoryTree } from './useCategoryTree';

/**
 * Orquestrador reutilizável da manutenção do mercadológico: árvore lazy com
 * drag & drop, CRUD de categorias com desfazer/refazer e janelas de produtos.
 * Agnóstico de contexto — recebe as `urls` dos endpoints e as raízes iniciais.
 */
const props = defineProps<{
    urls: MercadologicoUrls;
    roots: TreeNode[];
}>();

const { t } = useT();

const store = useCategoryTree(props.urls);
store.seedRoots(props.roots);

const crud = useCategoryCrud(props.urls);
const history = useCategoryHistory();

function toastError(error: unknown): void {
    const message = error instanceof Error ? error.message : '';
    toast.error(message || t('app.landlord.mercadologico.messages.action_failed'));
}

// ── Atualizações da árvore compartilhadas por CRUD/histórico ──
async function applyCreate(parentId: string | null): Promise<void> {
    if (parentId === null) {
        await store.refresh(null);

        return;
    }

    store.adjustChildrenCount(parentId, 1);
    store.markExpanded(parentId);
    await store.refresh(parentId);
}

async function applyRemove(parentId: string | null): Promise<void> {
    if (parentId === null) {
        await store.refresh(null);

        return;
    }

    store.adjustChildrenCount(parentId, -1);
    await store.refresh(parentId);
}

// ── Mover (arraste) com confirmação de impacto + histórico ──
type PendingMove = { draggedId: string; target: DropTarget };
const pendingMove = ref<PendingMove | null>(null);
const moving = ref(false);

function requestMove(draggedId: string, target: DropTarget): void {
    window.setTimeout(() => {
        pendingMove.value = { draggedId, target };
    }, 0);
}

const drag = useCategoryDrag(store, requestMove);

const pendingNode = computed(
    () => store.getNode(pendingMove.value?.draggedId ?? '')?.node ?? null,
);
const pendingTargetIsRoot = computed(
    () => pendingMove.value?.target === ROOT_TARGET,
);
const pendingTargetName = computed(() => {
    if (!pendingMove.value || pendingTargetIsRoot.value) {
        return '';
    }

    return store.getNode(pendingMove.value.target)?.node.name ?? '';
});

function cancelMove(): void {
    pendingMove.value = null;
}

/** Executa o POST de mover como Promise (usado pela confirmação e pelo histórico). */
function moveViaApi(categoryId: string, targetId: string | null): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            props.urls.move(categoryId),
            { target_category_id: targetId },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () => reject(new Error('move')),
            },
        );
    });
}

async function confirmMove(): Promise<void> {
    const pending = pendingMove.value;

    if (!pending) {
        return;
    }

    const originParentId = store.getNode(pending.draggedId)?.parentId ?? null;
    const targetId = pending.target === ROOT_TARGET ? null : pending.target;
    const draggedId = pending.draggedId;

    moving.value = true;

    try {
        await moveViaApi(draggedId, targetId);
        await store.refresh(originParentId);
        await store.refresh(targetId);

        history.record({
            label: 'move',
            undo: async () => {
                await moveViaApi(draggedId, originParentId);
                await store.refresh(targetId);
                await store.refresh(originParentId);
            },
            redo: async () => {
                await moveViaApi(draggedId, targetId);
                await store.refresh(originParentId);
                await store.refresh(targetId);
            },
        });

        pendingMove.value = null;
    } catch (error) {
        toastError(error);
    } finally {
        moving.value = false;
    }
}

// ── Criar / editar (form dialog) ──
const formOpen = ref(false);
const formMode = ref<'create-root' | 'create-child' | 'edit'>('create-root');
const formParent = ref<TreeNode | null>(null);
const formInitial = ref<CategoryFormData | null>(null);
const formEditingId = ref<string | null>(null);
const formSaving = ref(false);

function openCreateRoot(): void {
    formMode.value = 'create-root';
    formParent.value = null;
    formInitial.value = null;
    formEditingId.value = null;
    formOpen.value = true;
}

function openAddChild(node: TreeNode): void {
    formMode.value = 'create-child';
    formParent.value = node;
    formInitial.value = null;
    formEditingId.value = null;
    formOpen.value = true;
}

function openEdit(node: TreeNode): void {
    formMode.value = 'edit';
    formParent.value = null;
    formEditingId.value = node.id;
    formInitial.value = { name: node.name, codigo: node.codigo, status: node.status };
    formOpen.value = true;
}

async function submitForm(data: CategoryFormData): Promise<void> {
    formSaving.value = true;

    try {
        if (formMode.value === 'edit' && formEditingId.value) {
            const id = formEditingId.value;
            const before = formInitial.value ?? { name: '', codigo: null, status: 'draft' };

            const node = await crud.update(id, data);
            store.updateNodeData(node);
            formOpen.value = false;
            toast.success(t('app.landlord.mercadologico.messages.updated'));

            history.record({
                label: 'edit',
                undo: async () => {
                    store.updateNodeData(await crud.update(id, before));
                },
                redo: async () => {
                    store.updateNodeData(await crud.update(id, data));
                },
            });
        } else {
            const parentId =
                formMode.value === 'create-child' ? (formParent.value?.id ?? null) : null;

            const node = await crud.create(parentId, data);
            await applyCreate(parentId);
            formOpen.value = false;
            toast.success(t('app.landlord.mercadologico.messages.created'));

            history.record({
                label: 'create',
                undo: async () => {
                    await crud.remove(node.id);
                    await applyRemove(parentId);
                },
                redo: async () => {
                    await crud.restore(node.id);
                    await applyCreate(parentId);
                },
            });
        }
    } catch (error) {
        toastError(error);
    } finally {
        formSaving.value = false;
    }
}

// ── Excluir (confirmação) ──
const pendingDelete = ref<TreeNode | null>(null);
const deleting = ref(false);

function requestDelete(node: TreeNode): void {
    pendingDelete.value = node;
}

async function confirmDelete(): Promise<void> {
    const node = pendingDelete.value;

    if (!node) {
        return;
    }

    const parentId = store.getNode(node.id)?.parentId ?? null;
    deleting.value = true;

    try {
        await crud.remove(node.id);
        await applyRemove(parentId);
        toast.success(t('app.landlord.mercadologico.messages.deleted'));

        history.record({
            label: 'delete',
            undo: async () => {
                await crud.restore(node.id);
                await applyCreate(parentId);
            },
            redo: async () => {
                await crud.remove(node.id);
                await applyRemove(parentId);
            },
        });

        pendingDelete.value = null;
    } catch (error) {
        toastError(error);
    } finally {
        deleting.value = false;
    }
}

// ── Desfazer / refazer ──
async function doUndo(): Promise<void> {
    try {
        if (await history.undo()) {
            toast.success(t('app.landlord.mercadologico.messages.undone'));
        }
    } catch (error) {
        toastError(error);
    }
}

async function doRedo(): Promise<void> {
    try {
        if (await history.redo()) {
            toast.success(t('app.landlord.mercadologico.messages.redone'));
        }
    } catch (error) {
        toastError(error);
    }
}

// ── Painéis flutuantes de produtos (um por categoria) ─────
type ProductsPanel = { refetch: () => void };

const openPanels = ref<OpenModal[]>([]);
const panelPositions = reactive<Record<string, { x: number; y: number }>>({});
const panelRefs: Record<string, ProductsPanel | null> = {};

function setPanelRef(categoryId: string, el: unknown): void {
    if (el) {
        panelRefs[categoryId] = el as ProductsPanel;
    } else {
        delete panelRefs[categoryId];
    }
}

function openProducts(node: TreeNode): void {
    if (openPanels.value.some((panel) => panel.categoryId === node.id)) {
        return;
    }

    const offset = openPanels.value.length % 8;
    panelPositions[node.id] = { x: 96 + offset * 34, y: 84 + offset * 34 };

    openPanels.value = [
        ...openPanels.value,
        { categoryId: node.id, categoryName: node.name },
    ];
}

function closePanel(categoryId: string): void {
    openPanels.value = openPanels.value.filter(
        (panel) => panel.categoryId !== categoryId,
    );
    delete panelPositions[categoryId];
    delete panelRefs[categoryId];
}

function otherCategoriesFor(categoryId: string): OpenModal[] {
    return openPanels.value.filter((panel) => panel.categoryId !== categoryId);
}

function onProductsMoved(payload: {
    from: string;
    to: string;
    count: number;
}): void {
    store.adjustProductsCount(payload.from, -payload.count);
    store.adjustProductsCount(payload.to, payload.count);
    panelRefs[payload.from]?.refetch();
    panelRefs[payload.to]?.refetch();
}
</script>

<template>
    <div>
        <div class="rounded-xl border border-border bg-card p-4">
            <!-- Toolbar -->
            <div class="mb-3 flex items-center justify-between gap-2 border-b pb-3">
                <div class="flex items-center gap-2">
                    <FolderTree class="size-5 text-muted-foreground" />
                    <h2 class="text-sm font-semibold">
                        {{ t('app.landlord.mercadologico.tree.roots_title') }}
                    </h2>
                </div>

                <div class="flex items-center gap-1.5">
                    <Button
                        variant="ghost"
                        size="icon"
                        :disabled="!history.canUndo.value"
                        :title="t('app.landlord.mercadologico.actions.undo')"
                        @click="doUndo"
                    >
                        <Undo2 class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :disabled="!history.canRedo.value"
                        :title="t('app.landlord.mercadologico.actions.redo')"
                        @click="doRedo"
                    >
                        <Redo2 class="size-4" />
                    </Button>
                    <Button size="sm" @click="openCreateRoot">
                        <Plus class="size-4" />
                        {{ t('app.landlord.mercadologico.actions.new_root') }}
                    </Button>
                </div>
            </div>

            <CategoryTree
                :store="store"
                :drag="drag"
                :on-open-products="openProducts"
                :on-add-child="openAddChild"
                :on-edit="openEdit"
                :on-delete="requestDelete"
            />
        </div>

        <!-- Confirmação de move com impacto -->
        <Dialog :open="pendingMove !== null" @update:open="(open) => !open && cancelMove()">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('app.landlord.mercadologico.move.confirm_title') }}</DialogTitle>
                    <DialogDescription>
                        <template v-if="pendingTargetIsRoot">
                            {{ t('app.landlord.mercadologico.move.confirm_message_root', { name: pendingNode?.name ?? '' }) }}
                        </template>
                        <template v-else>
                            {{ t('app.landlord.mercadologico.move.confirm_message', { name: pendingNode?.name ?? '', target: pendingTargetName }) }}
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <p v-if="pendingNode" class="text-sm text-muted-foreground">
                    {{ t('app.landlord.mercadologico.move.confirm_impact', {
                        descendants: String(pendingNode.children_count),
                        products: String(pendingNode.products_count),
                    }) }}
                </p>

                <DialogFooter>
                    <Button variant="outline" :disabled="moving" @click="cancelMove">
                        {{ t('app.landlord.mercadologico.move.cancel') }}
                    </Button>
                    <Button :disabled="moving" @click="confirmMove">
                        <Spinner v-if="moving" class="size-4" />
                        {{ t('app.landlord.mercadologico.move.confirm') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Confirmação de exclusão -->
        <Dialog :open="pendingDelete !== null" @update:open="(open) => !open && (pendingDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('app.landlord.mercadologico.delete.confirm_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('app.landlord.mercadologico.delete.confirm_message', { name: pendingDelete?.name ?? '' }) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" :disabled="deleting" @click="pendingDelete = null">
                        {{ t('app.landlord.mercadologico.delete.cancel') }}
                    </Button>
                    <Button variant="destructive" :disabled="deleting" @click="confirmDelete">
                        <Spinner v-if="deleting" class="size-4" />
                        {{ t('app.landlord.mercadologico.delete.confirm') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Formulário criar/editar -->
        <CategoryFormDialog
            v-model:open="formOpen"
            :mode="formMode"
            :parent-name="formParent?.name"
            :initial="formInitial"
            :saving="formSaving"
            @submit="submitForm"
        />

        <!-- Painéis flutuantes de produtos -->
        <FloatingProductsPanel
            v-for="panel in openPanels"
            :key="panel.categoryId"
            :ref="(el) => setPanelRef(panel.categoryId, el)"
            :urls="urls"
            :category="panel"
            :other-categories="otherCategoriesFor(panel.categoryId)"
            :initial-position="panelPositions[panel.categoryId] ?? { x: 96, y: 84 }"
            @close="closePanel(panel.categoryId)"
            @moved="onProductsMoved"
        />
    </div>
</template>
