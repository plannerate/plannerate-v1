<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Loader2, Pencil, X } from 'lucide-vue-next';
import { ref } from 'vue';
import ListPage from '@/components/ListPage.vue';
import ColumnHeader from '@/components/table/columns/ColumnHeader.vue';
import ColumnStatusBadge from '@/components/table/columns/ColumnStatusBadge.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type DimensionRow = {
    id: string;
    name: string | null;
    ean: string | null;
    codigo_erp: string | null;
    width: string | number | null;
    height: string | number | null;
    depth: string | number | null;
    weight: string | number | null;
    unit: string | null;
    dimension_status: 'draft' | 'published' | null;
};

type EditingRow = {
    width: string;
    height: string;
    depth: string;
    weight: string;
    unit: string;
    dimension_status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    products?: Paginator<DimensionRow>;
    filters: {
        search: string;
        dimension_status: string;
    };
}>();

const { t } = useT();
const { meta, rows, loading } = useDeferredPaginator(() => props.products, 20);

const indexPath = `/dimensions`;
const updatePath = (id: string) => `/dimensions/${id}`;

const editingId = ref<string | null>(null);
const editingData = ref<EditingRow | null>(null);
const savingId = ref<string | null>(null);

function startEdit(row: DimensionRow): void {
    editingId.value = row.id;
    editingData.value = {
        width: row.width !== null ? String(row.width) : '',
        height: row.height !== null ? String(row.height) : '',
        depth: row.depth !== null ? String(row.depth) : '',
        weight: row.weight !== null ? String(row.weight) : '',
        unit: row.unit ?? 'cm',
        dimension_status: row.dimension_status ?? 'draft',
    };
}

function cancelEdit(): void {
    editingId.value = null;
    editingData.value = null;
}

function saveEdit(id: string): void {
    if (!editingData.value || savingId.value) {
        return;
    }

    savingId.value = id;

    router.patch(
        updatePath(id),
        { ...editingData.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
                editingData.value = null;
            },
            onFinish: () => {
                savingId.value = null;
            },
        },
    );
}

function handleKeydown(event: KeyboardEvent, id: string): void {
    if (event.key === 'Enter') {
        saveEdit(id);
    } else if (event.key === 'Escape') {
        cancelEdit();
    }
}

const pageMeta = useCrudPageMeta({
    headTitle: 'Dimensões',
    title: 'Dimensões',
    description: 'Gerencie as dimensões dos produtos diretamente na lista.',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: 'Dimensões', href: indexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <ListPage :meta="meta" label="produto" :action="indexPath" :clear-href="indexPath"
            :search-value="props.filters.search" :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')" :clear-label="t('app.tenant.common.clear_filters')">
            <template #filters>
                <select name="dimension_status" :value="filters.dimension_status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">Todos</option>
                    <option value="draft">Rascunho</option>
                    <option value="published">Publicado</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <ColumnHeader field="codigo_erp">Cód. ERP</ColumnHeader>
                        <ColumnHeader field="ean">EAN Produto</ColumnHeader>
                        <ColumnHeader field="width">Largura</ColumnHeader>
                        <ColumnHeader field="height">Altura</ColumnHeader>
                        <ColumnHeader field="depth">Profundidade</ColumnHeader>
                        <th class="px-4 py-3 font-medium">Peso</th>
                        <th class="px-4 py-3 font-medium">Unidade</th>
                        <ColumnHeader field="dimension_status">Status</ColumnHeader>
                        <th class="w-20 px-4 py-3 text-center font-medium">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="loading">
                        <TableLoadingSkeleton :columns="9" :rows="8" />
                    </template>
                    <tr v-else-if="rows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="9">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                        :class="{ 'bg-primary/5 even:bg-primary/5': editingId === row.id }">
                        <td class="px-4 py-2">{{ row.codigo_erp ?? '-' }}</td>
                        <td class="px-4 py-2">{{ row.ean ?? '-' }}</td>

                        <template v-if="editingId === row.id && editingData">
                            <td class="px-2 py-1">
                                <input v-model="editingData.width" type="number" min="0" step="0.01"
                                    class="h-8 w-20 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    placeholder="Larg." @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <input v-model="editingData.height" type="number" min="0" step="0.01"
                                    class="h-8 w-20 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    placeholder="Alt." @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <input v-model="editingData.depth" type="number" min="0" step="0.01"
                                    class="h-8 w-20 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    placeholder="Prof." @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <input v-model="editingData.weight" type="number" min="0" step="0.01"
                                    class="h-8 w-20 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    placeholder="Peso" @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <input v-model="editingData.unit"
                                    class="h-8 w-16 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    placeholder="cm" maxlength="20" @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <select v-model="editingData.dimension_status"
                                    class="h-8 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20">
                                    <option value="draft">Rascunho</option>
                                    <option value="published">Publicado</option>
                                </select>
                            </td>
                        </template>

                        <template v-else>
                            <td class="px-4 py-2">{{ row.width ?? '-' }}</td>
                            <td class="px-4 py-2">{{ row.height ?? '-' }}</td>
                            <td class="px-4 py-2">{{ row.depth ?? '-' }}</td>
                            <td class="px-4 py-2">{{ row.weight ?? '-' }}</td>
                            <td class="px-4 py-2">{{ row.unit ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <ColumnStatusBadge :status="row.dimension_status ?? 'draft'" />
                            </td>
                        </template>

                        <td class="px-4 py-2 text-center">
                            <template v-if="editingId === row.id">
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" :disabled="savingId === row.id"
                                        class="flex size-7 items-center justify-center rounded bg-primary text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                                        title="Salvar" @click="saveEdit(row.id)">
                                        <Loader2 v-if="savingId === row.id" class="size-3.5 animate-spin" />
                                        <Check v-else class="size-3.5" />
                                    </button>
                                    <button type="button"
                                        class="flex size-7 items-center justify-center rounded border border-border bg-background transition hover:bg-muted"
                                        title="Cancelar" @click="cancelEdit">
                                        <X class="size-3.5" />
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <button type="button"
                                    class="flex size-7 items-center justify-center rounded border border-border bg-background transition hover:bg-muted"
                                    title="Editar dimensões" @click="startEdit(row)">
                                    <Pencil class="size-3.5" />
                                </button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
