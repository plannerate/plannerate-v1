<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import StoreController from '@/actions/App/Http/Controllers/Tenant/StoreController';
import { show as gondolaPdfShow } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import KankanNavigationLinks from '@/components/KankanNavigationLinks.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { editor as tenantEditorPlanogramGondolas } from '@/routes/tenant/planograms/gondolas';

type StoreMapRegion = {
    id: string;
    label: string | null;
    x: number;
    y: number;
    width: number;
    height: number;
    shape: 'rectangle' | 'circle';
    gondola: {
        id: string;
        name: string;
        execution_id: string | null;
        execution_started: boolean;
        can_open_editor: boolean;
        can_view: boolean;
    } | null;
};

type StoreMapCard = {
    id: string;
    name: string;
    can_edit_store: boolean;
    map_image_url: string | null;
    regions: StoreMapRegion[];
};

const props = defineProps<{
    subdomain: string;
    store_maps: StoreMapCard[];
    filters: {
        search: string;
        store_id: string;
        status: 'all' | 'clickable' | 'pending' | 'blocked';
        only_editable: boolean;
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const mapsPath = PlanogramController.maps.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const search = ref(props.filters.search ?? '');
const selectedStoreId = ref<string>(props.filters.store_id ?? '');
const statusFilter = ref<'all' | 'clickable' | 'pending' | 'blocked'>(props.filters.status ?? 'all');
const onlyEditableStores = ref(props.filters.only_editable ?? false);

const storeOptions = computed(() => props.filter_options.stores ?? []);

function regionHref(region: StoreMapRegion): string | null {
    if (!region.gondola || !region.gondola.execution_started) {
        return null;
    }

    if (region.gondola.can_open_editor) {
        return tenantEditorPlanogramGondolas.url({
            subdomain: props.subdomain,
            record: region.gondola.id,
        });
    }

    if (region.gondola.can_view) {
        return gondolaPdfShow.url(region.gondola.id);
    }

    return null;
}

function isRegionClickable(region: StoreMapRegion): boolean {
    return regionHref(region) !== null;
}

function clickableCount(storeMap: StoreMapCard): number {
    return storeMap.regions.filter((region) => isRegionClickable(region)).length;
}

function pendingCount(storeMap: StoreMapCard): number {
    return storeMap.regions.filter((region) => region.gondola && !region.gondola.execution_started).length;
}

function blockedCount(storeMap: StoreMapCard): number {
    return storeMap.regions.filter((region) => region.gondola?.execution_started && !isRegionClickable(region)).length;
}

const totals = computed(() => {
    const stores = props.store_maps.length;
    const regions = props.store_maps.reduce((total, storeMap) => total + storeMap.regions.length, 0);
    const clickableRegions = props.store_maps.reduce(
        (total, storeMap) => total + storeMap.regions.filter((region) => isRegionClickable(region)).length,
        0,
    );
    const pendingRegions = props.store_maps.reduce(
        (total, storeMap) => total + storeMap.regions.filter((region) => region.gondola && !region.gondola.execution_started).length,
        0,
    );

    return { stores, regions, clickableRegions, pendingRegions };
});

function clearFilters(): void {
    search.value = '';
    selectedStoreId.value = '';
    statusFilter.value = 'all';
    onlyEditableStores.value = false;
    submitFilters();
}

function selectStatusFilter(filter: 'all' | 'clickable' | 'pending' | 'blocked'): void {
    statusFilter.value = filter;
    submitFilters();
}

function submitFilters(): void {
    router.get(mapsPath, {
        search: search.value || undefined,
        store_id: selectedStoreId.value || undefined,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
        only_editable: onlyEditableStores.value ? '1' : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const pageMeta = useCrudPageMeta({
    headTitle: 'Maps',
    title: 'Maps',
    description: 'Visualização de planogramas em mapa.',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
        { title: 'Maps', href: '/planograms/maps' },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="PlanogramController.create.url(props.subdomain)">
                    {{ t('app.tenant.planograms.actions.new') }}
                </NewActionButton>
            </div>
        </template>
        <KankanNavigationLinks :subdomain="props.subdomain" />
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <button
                type="button"
                class="rounded-lg border border-border bg-card p-3 text-left transition hover:bg-muted/30"
                :class="statusFilter === 'all' ? 'border-primary/60 ring-2 ring-primary/20' : ''"
                @click="selectStatusFilter('all')"
            >
                <p class="text-xs text-muted-foreground">Lojas com mapa</p>
                <p class="text-lg font-semibold text-foreground">{{ totals.stores }}</p>
            </button>
            <div class="rounded-lg border border-border bg-card p-3">
                <p class="text-xs text-muted-foreground">Regiões no total</p>
                <p class="text-lg font-semibold text-foreground">{{ totals.regions }}</p>
            </div>
            <button
                type="button"
                class="rounded-lg border border-border bg-card p-3 text-left transition hover:bg-muted/30"
                :class="statusFilter === 'clickable' ? 'border-primary/60 ring-2 ring-primary/20' : ''"
                @click="selectStatusFilter('clickable')"
            >
                <p class="text-xs text-muted-foreground">Regiões clicáveis</p>
                <p class="text-lg font-semibold text-primary">{{ totals.clickableRegions }}</p>
            </button>
            <button
                type="button"
                class="rounded-lg border border-border bg-card p-3 text-left transition hover:bg-muted/30"
                :class="statusFilter === 'pending' ? 'border-primary/60 ring-2 ring-primary/20' : ''"
                @click="selectStatusFilter('pending')"
            >
                <p class="text-xs text-muted-foreground">Execução não iniciada</p>
                <p class="text-lg font-semibold text-amber-600">{{ totals.pendingRegions }}</p>
            </button>
        </div>

        <div class="mb-4 flex flex-col gap-3 rounded-lg border border-border bg-card p-3 lg:flex-row lg:items-center">
            <input
                v-model="search"
                type="text"
                placeholder="Buscar loja..."
                class="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 lg:max-w-xs"
                @keydown.enter.prevent="submitFilters"
            >
            <select
                v-model="selectedStoreId"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                @change="submitFilters"
            >
                <option value="">Todas as lojas</option>
                <option v-for="store in storeOptions" :key="store.id" :value="store.id">
                    {{ store.name }}
                </option>
            </select>
            <select
                v-model="statusFilter"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                @change="submitFilters"
            >
                <option value="all">Todas as lojas</option>
                <option value="clickable">Com regiões clicáveis</option>
                <option value="pending">Com execução não iniciada</option>
                <option value="blocked">Com restrição de permissão</option>
            </select>
            <label class="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-background px-3 text-sm text-foreground">
                <input v-model="onlyEditableStores" type="checkbox" class="size-4 rounded border-border" @change="submitFilters" />
                Somente lojas editáveis
            </label>
            <button
                type="button"
                class="h-9 rounded-lg bg-primary px-3 text-sm text-primary-foreground transition hover:bg-primary/90"
                @click="submitFilters"
            >
                Aplicar filtros
            </button>
            <button
                type="button"
                class="h-9 rounded-lg border border-border px-3 text-sm text-foreground transition hover:bg-muted lg:ml-auto"
                @click="clearFilters"
            >
                Limpar filtros
            </button>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs">
            <div class="inline-flex items-center gap-2 rounded-md border border-border bg-card px-2.5 py-1.5 text-foreground">
                <span class="inline-block h-3 w-3 rounded-sm border border-primary bg-primary/40 shadow-[0_0_0_2px_rgba(59,130,246,0.35)]" />
                <span>Com link</span>
            </div>
            <div class="inline-flex items-center gap-2 rounded-md border border-border bg-card px-2.5 py-1.5 text-muted-foreground">
                <span class="inline-block h-3 w-3 rounded-sm border border-muted-foreground/40 bg-muted/60" />
                <span>Sem link</span>
            </div>
        </div>

        <div v-if="props.store_maps.length === 0" class="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
            Nenhum mapa de loja disponível.
        </div>
        <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article
                v-for="storeMap in props.store_maps"
                :key="storeMap.id"
                class="rounded-lg border border-border bg-card p-4"
            >
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ storeMap.name }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{ storeMap.regions.length }} regiões mapeadas
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5 text-[11px]">
                            <span class="rounded-md bg-primary/10 px-2 py-0.5 text-primary">
                                {{ clickableCount(storeMap) }} clicáveis
                            </span>
                            <span class="rounded-md bg-amber-500/10 px-2 py-0.5 text-amber-700 dark:text-amber-400">
                                {{ pendingCount(storeMap) }} pendentes
                            </span>
                            <span class="rounded-md bg-muted px-2 py-0.5 text-muted-foreground">
                                {{ blockedCount(storeMap) }} bloqueadas
                            </span>
                        </div>
                    </div>
                    <a
                        v-if="storeMap.can_edit_store"
                        :href="StoreController.edit.url({ subdomain: props.subdomain, store: storeMap.id })"
                        class="inline-flex h-8 items-center rounded-md border border-border px-3 text-xs font-medium text-foreground transition hover:bg-muted"
                    >
                        Editar loja
                    </a>
                </div>

                <div class="overflow-hidden rounded-md border border-border bg-muted/20 p-2">
                    <div class="relative inline-block origin-top-left" style="zoom: 0.78;">
                        <img
                            :src="storeMap.map_image_url ?? ''"
                            :alt="`Mapa da loja ${storeMap.name}`"
                            class="max-w-none rounded-md"
                        >

                        <template v-for="region in storeMap.regions" :key="region.id">
                            <a
                                v-if="regionHref(region)"
                                :href="regionHref(region) ?? '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="absolute z-10 border-2 border-primary bg-primary/30 text-[11px] font-semibold text-primary-foreground shadow-[0_0_0_2px_rgba(59,130,246,0.35)] transition hover:scale-[1.02] hover:bg-primary/45 hover:shadow-[0_0_0_3px_rgba(59,130,246,0.55)]"
                                :class="region.shape === 'circle' ? 'rounded-full' : 'rounded-md'"
                                :style="{
                                    left: `${region.x}px`,
                                    top: `${region.y}px`,
                                    width: `${region.width}px`,
                                    height: `${region.height}px`,
                                }"
                                :title="region.gondola?.name ?? region.label ?? 'Região'"
                            >
                                <span class="pointer-events-none flex h-full w-full flex-col items-center justify-center text-center leading-tight">
                                    <span>{{ region.label ?? region.gondola?.name ?? 'Região' }}</span>
                                    <span class="mt-0.5 rounded-sm bg-black/25 px-1 text-[9px] uppercase tracking-wide text-white">
                                        Link
                                    </span>
                                </span>
                            </a>

                            <div
                                v-else
                                class="absolute border-2 border-muted-foreground/35 bg-muted/55 text-[11px] font-medium text-muted-foreground opacity-85"
                                :class="region.shape === 'circle' ? 'rounded-full' : 'rounded-md'"
                                :style="{
                                    left: `${region.x}px`,
                                    top: `${region.y}px`,
                                    width: `${region.width}px`,
                                    height: `${region.height}px`,
                                }"
                                :title="region.gondola?.execution_started ? 'Sem permissão para abrir' : 'Execução não iniciada'"
                            >
                                <span class="pointer-events-none flex h-full w-full items-center justify-center text-center">
                                    {{ region.label ?? region.gondola?.name ?? 'Região' }}
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
