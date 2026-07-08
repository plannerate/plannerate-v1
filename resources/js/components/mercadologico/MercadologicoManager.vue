<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FolderTree } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

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

import CategoryTree from './CategoryTree.vue';
import FloatingProductsPanel from './FloatingProductsPanel.vue';
import type { MercadologicoUrls, OpenModal, TreeNode } from './types';
import type { DropTarget } from './useCategoryDrag';
import { ROOT_TARGET } from './useCategoryDrag';
import { useCategoryDrag } from './useCategoryDrag';
import { useCategoryTree } from './useCategoryTree';

/**
 * Orquestrador reutilizável da manutenção do mercadológico.
 *
 * Contém toda a lógica de UI (árvore lazy, drag & drop com confirmação de
 * impacto e modais de produtos). É agnóstico de contexto: recebe as `urls` dos
 * endpoints e as raízes iniciais — por isso serve tanto ao landlord quanto a uma
 * futura página de tenant, que só precisam montar `<MercadologicoManager>`.
 */
const props = defineProps<{
    urls: MercadologicoUrls;
    roots: TreeNode[];
}>();

const { t } = useT();

// ── Árvore ────────────────────────────────────────────────
const store = useCategoryTree(props.urls);
store.seedRoots(props.roots);

// ── Drag & drop com confirmação de impacto ────────────────
type PendingMove = { draggedId: string; target: DropTarget };
const pendingMove = ref<PendingMove | null>(null);
const moving = ref(false);

function requestMove(draggedId: string, target: DropTarget): void {
    pendingMove.value = { draggedId, target };
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

function confirmMove(): void {
    const pending = pendingMove.value;

    if (!pending) {
        return;
    }

    const originParentId = store.getNode(pending.draggedId)?.parentId ?? null;
    const targetId = pending.target === ROOT_TARGET ? null : pending.target;

    moving.value = true;

    router.post(
        props.urls.move(pending.draggedId),
        { target_category_id: targetId },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: async () => {
                // Recarrega só os ramos afetados (origem e destino).
                await store.refresh(originParentId);
                await store.refresh(targetId);
                pendingMove.value = null;
            },
            onFinish: () => {
                moving.value = false;
            },
        },
    );
}

// ── Painéis flutuantes de produtos (um por categoria) ─────
type ProductsPanel = { refetch: () => void };

const openPanels = ref<OpenModal[]>([]);
const panelPositions = reactive<Record<string, { x: number; y: number }>>({});
// Refs de cada painel aberto, para recarregá-los após um move (sem remount).
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

    // Cascata leve para não empilhar as janelas exatamente umas sobre as outras.
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
    // Recarrega os painéis de origem e destino que estiverem abertos.
    panelRefs[payload.from]?.refetch();
    panelRefs[payload.to]?.refetch();
}
</script>

<template>
    <div>
        <div class="rounded-xl border border-border bg-card p-4">
            <div class="mb-3 flex items-center gap-2 border-b pb-3">
                <FolderTree class="size-5 text-muted-foreground" />
                <h2 class="text-sm font-semibold">
                    {{ t('app.landlord.mercadologico.tree.roots_title') }}
                </h2>
            </div>

            <CategoryTree
                :store="store"
                :drag="drag"
                :on-open-products="openProducts"
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

        <!-- Painéis flutuantes de produtos (não bloqueiam a navegação na árvore) -->
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
