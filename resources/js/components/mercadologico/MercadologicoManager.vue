<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    FolderTree,
    Link2,
    Link2Off,
    Plus,
    Redo2,
    Undo2,
} from 'lucide-vue-next';
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
    toast.error(
        message || t('app.landlord.mercadologico.messages.action_failed'),
    );
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
function moveViaApi(
    categoryId: string,
    targetId: string | null,
): Promise<void> {
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
    formInitial.value = {
        name: node.name,
        codigo: node.codigo,
        status: node.status,
    };
    formOpen.value = true;
}

async function submitForm(data: CategoryFormData): Promise<void> {
    formSaving.value = true;

    try {
        if (formMode.value === 'edit' && formEditingId.value) {
            const id = formEditingId.value;
            const before = formInitial.value ?? {
                name: '',
                codigo: null,
                status: 'draft',
            };

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
                formMode.value === 'create-child'
                    ? (formParent.value?.id ?? null)
                    : null;

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

// ── Linhas de ligação entre janelas pai → filha ───────────
type PanelGeometry = { x: number; y: number; width: number; height: number };

/** Geometria viva de cada janela (publicada pelo próprio painel ao mover/redimensionar). */
const panelGeometry = reactive<Record<string, PanelGeometry>>({});

/** Preferência de exibição das linhas (botão na toolbar). */
const showLinks = ref(true);

function onPanelGeometry(
    payload: { categoryId: string } & PanelGeometry,
): void {
    panelGeometry[payload.categoryId] = {
        x: payload.x,
        y: payload.y,
        width: payload.width,
        height: payload.height,
    };
}

type Connection = {
    key: string;
    path: string;
    parentName: string;
    childName: string;
};

/**
 * Monta a curva que liga a janela do pai à do filho, ancorando nos lados mais
 * próximos: usa as laterais quando a distância horizontal domina, e o topo/base
 * caso contrário — assim a linha nunca atravessa as janelas por dentro.
 */
function buildConnection(
    parent: OpenModal,
    child: OpenModal,
    a: PanelGeometry,
    b: PanelGeometry,
): Connection {
    const aCenter = { x: a.x + a.width / 2, y: a.y + a.height / 2 };
    const bCenter = { x: b.x + b.width / 2, y: b.y + b.height / 2 };
    const dx = bCenter.x - aCenter.x;
    const dy = bCenter.y - aCenter.y;

    let x1: number;
    let y1: number;
    let x2: number;
    let y2: number;
    let c1x: number;
    let c1y: number;
    let c2x: number;
    let c2y: number;

    if (Math.abs(dx) >= Math.abs(dy)) {
        const toRight = dx >= 0;
        x1 = toRight ? a.x + a.width : a.x;
        y1 = aCenter.y;
        x2 = toRight ? b.x : b.x + b.width;
        y2 = bCenter.y;

        const curve = Math.max(40, Math.abs(x2 - x1) / 2);
        c1x = x1 + (toRight ? curve : -curve);
        c1y = y1;
        c2x = x2 - (toRight ? curve : -curve);
        c2y = y2;
    } else {
        const toBottom = dy >= 0;
        x1 = aCenter.x;
        y1 = toBottom ? a.y + a.height : a.y;
        x2 = bCenter.x;
        y2 = toBottom ? b.y : b.y + b.height;

        const curve = Math.max(40, Math.abs(y2 - y1) / 2);
        c1x = x1;
        c1y = y1 + (toBottom ? curve : -curve);
        c2x = x2;
        c2y = y2 - (toBottom ? curve : -curve);
    }

    return {
        key: `${parent.categoryId}->${child.categoryId}`,
        path: `M ${x1} ${y1} C ${c1x} ${c1y}, ${c2x} ${c2y}, ${x2} ${y2}`,
        parentName: parent.categoryName,
        childName: child.categoryName,
    };
}

/**
 * Ligações a desenhar: uma para cada janela aberta cuja categoria-pai (segundo a
 * árvore) também esteja aberta. Só liga pai direto — não ancestrais distantes.
 */
const connections = computed<Connection[]>(() => {
    if (!showLinks.value) {
        return [];
    }

    const byId = new Map(
        openPanels.value.map((panel) => [panel.categoryId, panel]),
    );
    const result: Connection[] = [];

    for (const child of openPanels.value) {
        const parentId = store.getNode(child.categoryId)?.parentId ?? null;

        if (parentId === null) {
            continue;
        }

        const parent = byId.get(parentId);
        const parentGeometry = panelGeometry[parentId];
        const childGeometry = panelGeometry[child.categoryId];

        if (!parent || !parentGeometry || !childGeometry) {
            continue;
        }

        result.push(
            buildConnection(parent, child, parentGeometry, childGeometry),
        );
    }

    return result;
});

function setPanelRef(categoryId: string, el: unknown): void {
    if (el) {
        panelRefs[categoryId] = el as ProductsPanel;
    } else {
        delete panelRefs[categoryId];
    }
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

/**
 * Posição de abertura da janela.
 *
 * Quando a janela da categoria-pai já está aberta, abre a filha em cascata ao
 * lado dela (direita por padrão; esquerda se não couber; abaixo se não couber
 * dos dois lados), deixando a hierarquia óbvia sem precisar arrastar. Sem pai
 * aberto, mantém a cascata padrão a partir do canto.
 */
function initialPositionFor(node: TreeNode): { x: number; y: number } {
    const parentId = store.getNode(node.id)?.parentId ?? null;
    const parent = parentId ? panelGeometry[parentId] : undefined;

    if (!parent) {
        const offset = openPanels.value.length % 8;

        return { x: 96 + offset * 34, y: 84 + offset * 34 };
    }

    // A filha usa a mesma largura do pai (mesma classe de painel).
    const margin = 16;
    const gap = 40;
    const maxX = Math.max(margin, window.innerWidth - parent.width - margin);
    const maxY = Math.max(margin, window.innerHeight - 160);

    let x = parent.x + parent.width + gap;
    let y = parent.y + 28;

    if (x > maxX) {
        const toLeft = parent.x - parent.width - gap;

        if (toLeft >= margin) {
            x = toLeft;
        } else {
            // Não cabe em nenhum dos lados: desce em degrau sob o pai.
            x = parent.x + 28;
            y = parent.y + 56;
        }
    }

    return { x: clamp(x, margin, maxX), y: clamp(y, margin, maxY) };
}

function openProducts(node: TreeNode): void {
    if (openPanels.value.some((panel) => panel.categoryId === node.id)) {
        return;
    }

    panelPositions[node.id] = initialPositionFor(node);

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
    delete panelGeometry[categoryId];
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
            <div
                class="mb-3 flex items-center justify-between gap-2 border-b pb-3"
            >
                <div class="flex items-center gap-2">
                    <FolderTree class="size-5 text-muted-foreground" />
                    <h2 class="text-sm font-semibold">
                        {{ t('app.landlord.mercadologico.tree.roots_title') }}
                    </h2>
                </div>

                <div class="flex items-center gap-1.5">
                    <Button
                        v-if="openPanels.length > 1"
                        variant="ghost"
                        size="icon"
                        :class="
                            showLinks ? 'text-primary' : 'text-muted-foreground'
                        "
                        :title="
                            showLinks
                                ? t(
                                      'app.landlord.mercadologico.actions.hide_links',
                                  )
                                : t(
                                      'app.landlord.mercadologico.actions.show_links',
                                  )
                        "
                        @click="showLinks = !showLinks"
                    >
                        <Link2 v-if="showLinks" class="size-4" />
                        <Link2Off v-else class="size-4" />
                    </Button>
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
        <Dialog
            :open="pendingMove !== null"
            @update:open="(open) => !open && cancelMove()"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        t('app.landlord.mercadologico.move.confirm_title')
                    }}</DialogTitle>
                    <DialogDescription>
                        <template v-if="pendingTargetIsRoot">
                            {{
                                t(
                                    'app.landlord.mercadologico.move.confirm_message_root',
                                    { name: pendingNode?.name ?? '' },
                                )
                            }}
                        </template>
                        <template v-else>
                            {{
                                t(
                                    'app.landlord.mercadologico.move.confirm_message',
                                    {
                                        name: pendingNode?.name ?? '',
                                        target: pendingTargetName,
                                    },
                                )
                            }}
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <p v-if="pendingNode" class="text-sm text-muted-foreground">
                    {{
                        t('app.landlord.mercadologico.move.confirm_impact', {
                            descendants: String(pendingNode.children_count),
                            products: String(pendingNode.products_count),
                        })
                    }}
                </p>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="moving"
                        @click="cancelMove"
                    >
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
        <Dialog
            :open="pendingDelete !== null"
            @update:open="(open) => !open && (pendingDelete = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        t('app.landlord.mercadologico.delete.confirm_title')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'app.landlord.mercadologico.delete.confirm_message',
                                { name: pendingDelete?.name ?? '' },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="deleting"
                        @click="pendingDelete = null"
                    >
                        {{ t('app.landlord.mercadologico.delete.cancel') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="confirmDelete"
                    >
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

        <!--
            Linhas de ligação pai → filha. Ficam em z-40 (abaixo das janelas, que
            são z-50) para "sair de trás" dos painéis, e sem eventos de ponteiro
            para não bloquear o arraste.
        -->
        <svg
            v-if="connections.length > 0"
            class="pointer-events-none fixed inset-0 z-40 size-full text-primary"
            aria-hidden="true"
        >
            <defs>
                <marker
                    id="merc-link-arrow"
                    markerWidth="9"
                    markerHeight="9"
                    refX="8"
                    refY="4.5"
                    orient="auto"
                    markerUnits="userSpaceOnUse"
                >
                    <path d="M 0 0 L 9 4.5 L 0 9 z" fill="currentColor" />
                </marker>
            </defs>

            <g v-for="connection in connections" :key="connection.key">
                <title>
                    {{ connection.parentName }} → {{ connection.childName }}
                </title>
                <!-- Traço de fundo: destaca a linha sobre qualquer conteúdo da página. -->
                <path
                    :d="connection.path"
                    fill="none"
                    stroke="var(--color-background)"
                    stroke-width="6"
                    stroke-linecap="round"
                    opacity="0.9"
                />
                <path
                    :d="connection.path"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-dasharray="6 4"
                    marker-end="url(#merc-link-arrow)"
                />
            </g>
        </svg>

        <!-- Painéis flutuantes de produtos -->
        <FloatingProductsPanel
            v-for="panel in openPanels"
            :key="panel.categoryId"
            :ref="(el) => setPanelRef(panel.categoryId, el)"
            :urls="urls"
            :category="panel"
            :other-categories="otherCategoriesFor(panel.categoryId)"
            :initial-position="
                panelPositions[panel.categoryId] ?? { x: 96, y: 84 }
            "
            @close="closePanel(panel.categoryId)"
            @moved="onProductsMoved"
            @geometry="onPanelGeometry"
        />
    </div>
</template>
