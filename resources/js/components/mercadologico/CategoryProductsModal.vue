<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ArrowRightLeft, PackageOpen, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';

import type { MercadologicoUrls, OpenModal, ProductRow } from './types';

type ProductsResponse = {
    category: { id: string; name: string; full_path: string | null };
    products: {
        data: ProductRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

const props = defineProps<{
    urls: MercadologicoUrls;
    category: OpenModal;
    /** Outras categorias abertas — destinos possíveis para mover produtos. */
    otherCategories: OpenModal[];
}>();

const emit = defineEmits<{
    close: [];
    moved: [payload: { from: string; to: string; count: number }];
}>();

const { t } = useT();

const http = useHttp<Record<string, string>, ProductsResponse>();

const products = ref<ProductRow[]>([]);
const fullPath = ref<string | null>(null);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const search = ref('');
const selectedIds = ref<Set<string>>(new Set());
const targetId = ref('');
const moving = ref(false);

const hasMore = computed(() => page.value < lastPage.value);
const selectedCount = computed(() => selectedIds.value.size);
const allVisibleSelected = computed(
    () =>
        products.value.length > 0 &&
        products.value.every((product) => selectedIds.value.has(product.id)),
);
const canMove = computed(
    () => selectedCount.value > 0 && targetId.value !== '' && !moving.value,
);

/**
 * Carrega uma página de produtos da categoria (append quando paginando).
 */
async function fetchProducts(append = false): Promise<void> {
    loading.value = true;

    const url = new URL(
        props.urls.products(props.category.categoryId),
        window.location.origin,
    );
    url.searchParams.set('page', String(page.value));

    if (search.value.trim() !== '') {
        url.searchParams.set('search', search.value.trim());
    }

    try {
        await http.get(url.toString());
        const payload = http.response;

        if (!payload) {
            return;
        }

        fullPath.value = payload.category.full_path;
        total.value = payload.products.total;
        lastPage.value = payload.products.last_page;
        products.value = append
            ? [...products.value, ...payload.products.data]
            : payload.products.data;
    } finally {
        loading.value = false;
    }
}

function toggle(id: string): void {
    const next = new Set(selectedIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selectedIds.value = next;
}

function toggleAll(): void {
    if (allVisibleSelected.value) {
        selectedIds.value = new Set();

        return;
    }

    selectedIds.value = new Set(products.value.map((product) => product.id));
}

async function loadMore(): Promise<void> {
    if (!hasMore.value || loading.value) {
        return;
    }

    page.value += 1;
    await fetchProducts(true);
}

/**
 * Move os produtos selecionados para a categoria de destino escolhida.
 */
function moveSelected(): void {
    if (!canMove.value) {
        return;
    }

    const ids = Array.from(selectedIds.value);
    const to = targetId.value;
    moving.value = true;

    router.post(
        props.urls.moveProducts(),
        { product_ids: ids, target_category_id: to },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('moved', {
                    from: props.category.categoryId,
                    to,
                    count: ids.length,
                });
                selectedIds.value = new Set();
                targetId.value = '';
                page.value = 1;
                void fetchProducts();
            },
            onFinish: () => {
                moving.value = false;
            },
        },
    );
}

function onOpenChange(open: boolean): void {
    if (!open) {
        emit('close');
    }
}

watchDebounced(
    search,
    () => {
        page.value = 1;
        void fetchProducts();
    },
    { debounce: 300 },
);

onMounted(() => {
    void fetchProducts();
});
</script>

<template>
    <Dialog :open="true" @update:open="onOpenChange">
        <DialogContent class="flex max-h-[85vh] flex-col gap-0 p-0 sm:max-w-lg">
            <DialogHeader class="border-b px-5 py-4">
                <DialogTitle class="truncate">
                    {{ t('app.landlord.mercadologico.products.modal_title', { name: category.categoryName }) }}
                </DialogTitle>
                <DialogDescription v-if="fullPath" class="truncate text-xs">
                    {{ fullPath }}
                </DialogDescription>
            </DialogHeader>

            <!-- Busca -->
            <div class="border-b px-5 py-3">
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('app.landlord.mercadologico.products.search')"
                        class="h-9 w-full rounded-lg border border-input bg-background pr-3 pl-8 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    />
                </div>

                <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                    <label class="flex cursor-pointer items-center gap-2">
                        <Checkbox
                            :model-value="allVisibleSelected"
                            @update:model-value="toggleAll"
                        />
                        <span>{{ t('app.landlord.mercadologico.products.selected', { count: String(selectedCount) }) }}</span>
                    </label>
                    <span>{{ total }}</span>
                </div>
            </div>

            <!-- Lista -->
            <ScrollArea class="min-h-0 flex-1">
                <div class="px-3 py-2">
                    <div v-if="loading && products.length === 0" class="flex justify-center py-10">
                        <Spinner class="size-5" />
                    </div>

                    <div
                        v-else-if="products.length === 0"
                        class="flex flex-col items-center gap-2 py-10 text-sm text-muted-foreground"
                    >
                        <PackageOpen class="size-8 opacity-30" />
                        {{ t('app.landlord.mercadologico.products.empty') }}
                    </div>

                    <template v-else>
                        <label
                            v-for="product in products"
                            :key="product.id"
                            class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 transition hover:bg-muted/60"
                        >
                            <Checkbox
                                :model-value="selectedIds.has(product.id)"
                                @update:model-value="toggle(product.id)"
                            />
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                alt=""
                                class="size-9 shrink-0 rounded border border-border/60 object-contain"
                            />
                            <div v-else class="flex size-9 shrink-0 items-center justify-center rounded border border-border/60 bg-muted">
                                <PackageOpen class="size-4 text-muted-foreground/50" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ product.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    <span v-if="product.ean">{{ t('app.landlord.mercadologico.products.columns.ean') }}: {{ product.ean }}</span>
                                    <span v-if="product.codigo_erp"> · {{ product.codigo_erp }}</span>
                                </p>
                            </div>
                        </label>

                        <div v-if="hasMore" class="px-2 py-2">
                            <Button variant="outline" size="sm" class="w-full" :disabled="loading" @click="loadMore">
                                <Spinner v-if="loading" class="size-4" />
                                {{ t('app.landlord.mercadologico.products.load_more') }}
                            </Button>
                        </div>
                    </template>
                </div>
            </ScrollArea>

            <!-- Rodapé: mover selecionados -->
            <div class="flex items-center gap-2 border-t px-5 py-3">
                <ArrowRightLeft class="size-4 shrink-0 text-muted-foreground" />
                <select
                    v-model="targetId"
                    class="h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:opacity-50"
                    :disabled="otherCategories.length === 0"
                >
                    <option value="">
                        {{ otherCategories.length === 0 ? t('app.landlord.mercadologico.products.open_target_hint') : t('app.landlord.mercadologico.products.select_target') }}
                    </option>
                    <option v-for="option in otherCategories" :key="option.categoryId" :value="option.categoryId">
                        {{ option.categoryName }}
                    </option>
                </select>
                <Button size="sm" :disabled="!canMove" @click="moveSelected">
                    <Spinner v-if="moving" class="size-4" />
                    {{ t('app.landlord.mercadologico.products.move_selected') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
