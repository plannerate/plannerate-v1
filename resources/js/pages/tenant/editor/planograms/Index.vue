<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen, CalendarRange, LayoutTemplate, Store } from 'lucide-vue-next';
import ClientPlanogramController from '@/actions/App/Http/Controllers/Tenant/Editor/ClientPlanogramController';
import ListTablePage from '@/components/ListPage.vue';
import type ListPage from '@/components/ListPage.vue';
import { ColumnDate, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import WayfinderLink from '@/components/WayfinderLink.vue';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginator } from '@/types';
import { ref } from 'vue';

type PlanogramRow = {
    id: string;
    name: string | null;
    type: 'realograma' | 'planograma';
    store_id: string | null;
    store: string | null;
    category: string | null;
    start_date: string | null;
    end_date: string | null;
    description: string | null;
};

const props = defineProps<{
    planograms?: Paginator<PlanogramRow>;
    filters: {
        search: string;
        store_id: string;
        category_id: string;
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
    stats: {
        total_published: number;
        total_stores: number;
        active_count: number;
    };
}>();

const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);
const { meta: planogramsMeta, rows: planogramsRows, loading: planogramsLoading } = useDeferredPaginator(() => props.planograms, 10);

const indexPath = ClientPlanogramController.index.url().replace(/^\/\/[^/]+/, '');
</script>

<template>
    <AppLayout
        :breadcrumbs="[{ title: 'Planogramas Publicados', href: indexPath }]"
        :page-header="{ title: 'Planogramas Publicados', description: 'Visualize todos os planogramas disponíveis para sua loja.' }"
    >
        <Head title="Planogramas Publicados" />

        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 pb-8 sm:px-6">
            <!-- Stats cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <LayoutTemplate class="size-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-foreground">{{ props.stats.total_published }}</p>
                        <p class="text-sm text-muted-foreground">Planogramas publicados</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <Store class="size-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-foreground">{{ props.stats.total_stores }}</p>
                        <p class="text-sm text-muted-foreground">Lojas com planogramas</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-green-500/10 text-green-600 dark:text-green-400">
                        <CalendarRange class="size-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-foreground">{{ props.stats.active_count }}</p>
                        <p class="text-sm text-muted-foreground">Ativos no momento</p>
                    </div>
                </div>
            </div>

            <!-- List -->
            <ListTablePage
                ref="listPageRef"
                :meta="planogramsMeta"
                label="planograma"
                :action="indexPath"
                :clear-href="indexPath"
                :search-value="props.filters.search"
                search-placeholder="Buscar planograma..."
                filter-label="Filtrar"
                clear-label="Limpar filtros"
                :show-trashed-filter="false"
            >
                <template #filters>
                    <select
                        name="store_id"
                        :value="props.filters.store_id"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Todas as lojas</option>
                        <option v-for="store in props.filter_options.stores" :key="store.id" :value="store.id">
                            {{ store.name }}
                        </option>
                    </select>
                </template>

                <table class="w-full text-sm">
                    <thead class="bg-muted/30 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Planograma</th>
                            <th class="px-4 py-3 font-medium">Loja</th>
                            <th class="px-4 py-3 font-medium">Tipo</th>
                            <th class="px-4 py-3 font-medium">Vigência</th>
                            <th class="px-4 py-3 font-medium ">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="planogramsLoading">
                            <TableLoadingSkeleton :columns="5" :rows="6" />
                        </template>
                        <tr v-else-if="planogramsRows.length === 0">
                            <td class="px-4 py-10 text-center text-muted-foreground" colspan="5">
                                Nenhum planograma publicado encontrado.
                            </td>
                        </tr>
                        <tr
                            v-for="planogram in planogramsRows"
                            :key="planogram.id"
                            class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <LayoutTemplate class="size-4 shrink-0 text-muted-foreground" />
                                    <ColumnLabel :label="planogram.name ?? '-'" :description="planogram.category" />
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <div v-if="planogram.store" class="flex items-center gap-1.5 text-muted-foreground">
                                    <Store class="size-3.5 shrink-0" />
                                    <span>{{ planogram.store }}</span>
                                </div>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>

                            <td class="px-4 py-3">
                                <Badge variant="secondary" class="capitalize">
                                    {{ planogram.type }}
                                </Badge>
                            </td>

                            <td class="px-4 py-3">
                                <ColumnDate :from="planogram.start_date" :to="planogram.end_date" />
                            </td>

                            <td class="px-4 py-3 ">
                                <Button variant="outline" size="sm" as-child>
                                    <WayfinderLink
                                        :href="ClientPlanogramController.gondolas.url({ planogram: planogram.id })"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <BookOpen class="size-3.5" />
                                        Ver gôndolas
                                    </WayfinderLink>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </ListTablePage>
        </div>
    </AppLayout>
</template>
