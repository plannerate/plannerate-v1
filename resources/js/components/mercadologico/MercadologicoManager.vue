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

import CategoryProductsModal from './CategoryProductsModal.vue';
import CategoryTree from './CategoryTree.vue';
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

// ── Modais de produtos (uma por categoria) ────────────────
const openModals = ref<OpenModal[]>([]);
const modalVersion = reactive<Record<string, number>>({});

function openProducts(node: TreeNode): void {
    if (openModals.value.some((modal) => modal.categoryId === node.id)) {
        return;
    }

    openModals.value = [
        ...openModals.value,
        { categoryId: node.id, categoryName: node.name },
    ];
}

function closeModal(categoryId: string): void {
    openModals.value = openModals.value.filter(
        (modal) => modal.categoryId !== categoryId,
    );
}

function otherCategoriesFor(categoryId: string): OpenModal[] {
    return openModals.value.filter((modal) => modal.categoryId !== categoryId);
}

function onProductsMoved(payload: {
    from: string;
    to: string;
    count: number;
}): void {
    store.adjustProductsCount(payload.from, -payload.count);
    store.adjustProductsCount(payload.to, payload.count);
    // Força a modal de destino (se aberta) a recarregar seus produtos.
    modalVersion[payload.to] = (modalVersion[payload.to] ?? 0) + 1;
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

        <!-- Modais de produtos por categoria -->
        <CategoryProductsModal
            v-for="modal in openModals"
            :key="`${modal.categoryId}-${modalVersion[modal.categoryId] ?? 0}`"
            :urls="urls"
            :category="modal"
            :other-categories="otherCategoriesFor(modal.categoryId)"
            @close="closeModal(modal.categoryId)"
            @moved="onProductsMoved"
        />
    </div>
</template>
