<script setup lang="ts">
import { computed, ref } from 'vue';
import type { CategoryNode } from '@/composables/useMercadologicoTree';
import type { HierarchyLevelNames } from '@/composables/useMercadologicoTree';
import MercadologicoDetailHeader from '@/components/mercadologico/MercadologicoDetailHeader.vue';
import MercadologicoDetailInfo from '@/components/mercadologico/MercadologicoDetailInfo.vue';
import MercadologicoDetailActions from '@/components/mercadologico/MercadologicoDetailActions.vue';
import MercadologicoDetailLevelsLegend from '@/components/mercadologico/MercadologicoDetailLevelsLegend.vue';
import MercadologicoDetailInstructions from '@/components/mercadologico/MercadologicoDetailInstructions.vue';
import MercadologicoAddSubcategoryDialog from '@/components/mercadologico/MercadologicoAddSubcategoryDialog.vue';
import MercadologicoDeleteDialogs from '@/components/mercadologico/MercadologicoDeleteDialogs.vue';
import MercadologicoDuplicateDialog from '@/components/mercadologico/MercadologicoDuplicateDialog.vue';
import MercadologicoEditDialog from '@/components/mercadologico/MercadologicoEditDialog.vue';

export interface CategoryUsage {
    children_count: number;
    products_count: number;
    planograms_count: number;
}

const props = withDefaults(
    defineProps<{
        categories: CategoryNode[];
        selected: CategoryNode | null;
        selectedCount?: number;
        usage?: CategoryUsage | null;
        hierarchyLevelNames?: HierarchyLevelNames | null;
        destroyUrl?: string;
        storeUrl?: string;
        duplicateUrl?: string;
        updateUrl?: string;
        redirectExpand?: string;
        redirectSelected?: string;
    }>(),
    { selectedCount: 0, usage: null, redirectExpand: '', redirectSelected: '' },
);

const emit = defineEmits<{
    (e: 'deleted'): void;
    (e: 'created'): void;
    (e: 'duplicated'): void;
    (e: 'updated'): void;
    (e: 'openProducts'): void;
}>();

const showDeleteConfirm = ref(false);
const showDeleteBlocked = ref(false);
const deleteBlockedMessage = ref('');
const showAddSubcategory = ref(false);
const showDuplicateConfirm = ref(false);
const showEdit = ref(false);

const canDelete = computed(() => {
    if (!props.usage) return true;
    const u = props.usage;
    return u.children_count === 0 && u.products_count === 0 && u.planograms_count === 0;
});

function buildDeleteBlockedMessage(): string {
    if (!props.usage) return '';
    const u = props.usage;
    const parts: string[] = [];
    if (u.children_count > 0) {
        parts.push(`possui ${u.children_count} subcategoria(s)`);
    }
    if (u.products_count > 0) {
        parts.push(`está relacionada a ${u.products_count} produto(s)`);
    }
    if (u.planograms_count > 0) {
        parts.push(`está relacionada a ${u.planograms_count} planograma(s)`);
    }
    return parts.length ? `Não é possível excluir esta categoria: ${parts.join(', ')}.` : '';
}

function openRemoveDialog() {
    if (!props.selected) return;
    if (!canDelete.value) {
        deleteBlockedMessage.value = buildDeleteBlockedMessage();
        showDeleteBlocked.value = true;
        return;
    }
    showDeleteConfirm.value = true;
}

function openAddSubcategory() {
    showAddSubcategory.value = true;
}

function openDuplicateConfirm() {
    showDuplicateConfirm.value = true;
}

function openEdit() {
    showEdit.value = true;
}
</script>

<template>
    <div class="flex w-72 shrink-0 flex-col border-l border-border bg-muted/20 overflow-y-auto">
        <MercadologicoDetailHeader
            :selected-count="selectedCount"
            :selected="selected"
        />
        <template v-if="selected && selectedCount === 1">
            <MercadologicoDetailInfo
                :selected="selected"
                :level-names="hierarchyLevelNames ?? undefined"
            />
            <MercadologicoDetailActions
                :store-url="storeUrl"
                :duplicate-url="duplicateUrl"
                :update-url="updateUrl"
                :products-count="usage?.products_count ?? 0"
                @add-subcategory="openAddSubcategory"
                @edit="openEdit"
                @duplicate="openDuplicateConfirm"
                @remove="openRemoveDialog"
                @open-products="emit('openProducts')"
            />
        </template>
        <MercadologicoDetailLevelsLegend
            :categories="categories"
            :level-names="hierarchyLevelNames ?? undefined"
        />
        <MercadologicoDetailInstructions />

        <MercadologicoAddSubcategoryDialog
            v-if="storeUrl"
            :open="showAddSubcategory"
            :selected="selected"
            :store-url="storeUrl"
            :redirect-expand="redirectExpand"
            :redirect-selected="redirectSelected"
            @update:open="showAddSubcategory = $event"
            @created="emit('created')"
        />
        <MercadologicoDeleteDialogs
            :show-delete-confirm="showDeleteConfirm"
            :show-delete-blocked="showDeleteBlocked"
            :delete-blocked-message="deleteBlockedMessage"
            :selected-id="selected?.id ?? null"
            :destroy-url="destroyUrl ?? ''"
            :usage="usage ?? null"
            :redirect-expand="redirectExpand"
            :redirect-selected="redirectSelected"
            @update:show-delete-confirm="showDeleteConfirm = $event"
            @update:show-delete-blocked="showDeleteBlocked = $event"
            @update:delete-blocked-message="deleteBlockedMessage = $event"
            @deleted="emit('deleted')"
        />
        <MercadologicoDuplicateDialog
            v-if="duplicateUrl"
            :open="showDuplicateConfirm"
            :selected="selected"
            :duplicate-url="duplicateUrl"
            :redirect-expand="redirectExpand"
            :redirect-selected="redirectSelected"
            @update:open="showDuplicateConfirm = $event"
            @duplicated="emit('duplicated')"
        />
        <MercadologicoEditDialog
            v-if="updateUrl"
            :open="showEdit"
            :selected="selected"
            :update-url="updateUrl"
            :redirect-expand="redirectExpand"
            :redirect-selected="redirectSelected"
            @update:open="showEdit = $event"
            @updated="emit('updated')"
        />
    </div>
</template>
