<script setup lang="ts">
import { Check, CheckCircle2, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/composables/useT';
import type { GroupingOption, ProductSearchResult } from './types';

const props = withDefaults(
    defineProps<{
        searchResults: ProductSearchResult[];
        searching: boolean;
        groupingOptions: GroupingOption[];
        addedProductEans?: string[];
        selectedGroupingId?: string | null;
    }>(),
    {
        addedProductEans: () => [],
        selectedGroupingId: null,
    },
);

const emit = defineEmits<{
    search: [grouping: string | null];
    'add-products': [
        items: Array<{ product: ProductSearchResult; grouping: string }>,
    ];
}>();

const { t } = useT();

const allGroupingsValue = '__all_groupings__';
const query = ref('');
const targetGrouping = ref(props.selectedGroupingId ?? allGroupingsValue);
const selectedIds = ref<string[]>([]);
const addedEanSet = computed(
    () => new Set(props.addedProductEans.map((ean) => normalizeEan(ean))),
);

const activeGroupingId = computed(() =>
    targetGrouping.value === allGroupingsValue ? '' : targetGrouping.value,
);

const selectedGroupingName = computed(() => {
    if (activeGroupingId.value === '') {
        return '';
    }

    return (
        props.groupingOptions.find(
            (option) => option.id === activeGroupingId.value,
        )?.name ?? ''
    );
});

const filteredSearchResults = computed(() => {
    const eanQuery = query.value.trim();

    if (eanQuery === '') {
        return props.searchResults;
    }

    return props.searchResults.filter((product) =>
        product.ean.includes(eanQuery),
    );
});

watch(targetGrouping, () => {
    selectedIds.value = [];
    emit('search', activeGroupingId.value || null);
});

watch(
    () => props.selectedGroupingId,
    (value) => {
        targetGrouping.value = value ?? allGroupingsValue;
    },
    { immediate: true },
);

watch(
    () => props.groupingOptions,
    () => {
        if (activeGroupingId.value === '') {
            return;
        }

        if (
            !props.groupingOptions.some(
                (option) => option.id === activeGroupingId.value,
            )
        ) {
            targetGrouping.value = allGroupingsValue;
        }
    },
);

watch(
    () => [props.addedProductEans, props.searchResults] as const,
    () => {
        selectedIds.value = selectedIds.value.filter((id) => {
            const product = props.searchResults.find((p) => p.id === id);

            return !product || !isAlreadyAdded(product);
        });
    },
    { deep: true },
);

function normalizeEan(ean: string): string {
    return ean.trim();
}

function toggleProduct(id: string): void {
    const product = props.searchResults.find((item) => item.id === id);

    if (product && isAlreadyAdded(product)) {
        return;
    }

    const idx = selectedIds.value.indexOf(id);

    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function isSelected(id: string): boolean {
    return selectedIds.value.includes(id);
}

function isAlreadyAdded(product: ProductSearchResult): boolean {
    return addedEanSet.value.has(normalizeEan(product.ean));
}

function addSelected(): void {
    if (!selectedGroupingName.value || selectedIds.value.length === 0) {
        return;
    }

    const idSet = new Set(selectedIds.value);
    const items = filteredSearchResults.value
        .filter((p) => idSet.has(p.id) && !isAlreadyAdded(p))
        .map((product) => ({ product, grouping: selectedGroupingName.value }));

    if (items.length === 0) {
        selectedIds.value = [];

        return;
    }

    emit('add-products', items);
    selectedIds.value = [];
}
</script>

<template>
    <div
        class="flex min-h-[24rem] min-w-0 flex-col overflow-hidden lg:max-h-[calc(100vh-17rem)]"
    >
        <div class="grid shrink-0 gap-2 pb-3">
            <!-- Search input -->
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    class="h-9 pl-8 text-sm"
                    :placeholder="
                        t(
                            'planogram-templates.product_search.search_placeholder',
                        )
                    "
                />
            </div>

            <!-- Grouping selector -->
            <Select
                v-model="targetGrouping"
                :disabled="groupingOptions.length === 0"
            >
                <SelectTrigger class="h-9 w-full max-w-full min-w-0 text-sm">
                    <SelectValue
                        :placeholder="
                            t(
                                'planogram-templates.product_search.grouping_placeholder',
                            )
                        "
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="allGroupingsValue">{{
                        t('planogram-templates.product_search.grouping_none')
                    }}</SelectItem>
                    <SelectItem
                        v-for="option in groupingOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p
                v-if="groupingOptions.length === 0"
                class="text-xs text-muted-foreground"
            >
                {{ t('planogram-templates.product_search.no_groupings_hint') }}
            </p>
        </div>

        <!-- Results list -->
        <div
            class="min-h-0 min-w-0 flex-1 overflow-y-auto rounded-md border border-border"
        >
            <div
                v-if="searching"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                {{ t('planogram-templates.product_search.searching') }}
            </div>
            <div
                v-else-if="!activeGroupingId"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                {{ t('planogram-templates.product_search.search_hint') }}
            </div>
            <div
                v-else-if="filteredSearchResults.length === 0"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                {{ t('planogram-templates.product_search.no_results') }}
            </div>
            <div v-else class="divide-y divide-border">
                <label
                    v-for="product in filteredSearchResults"
                    :key="product.id"
                    class="flex items-start gap-2.5 px-3 py-2 transition"
                    :class="{
                        'cursor-pointer hover:bg-muted/30':
                            !isAlreadyAdded(product),
                        'cursor-not-allowed bg-muted/30 opacity-75':
                            isAlreadyAdded(product),
                        'bg-primary/5': isSelected(product.id),
                    }"
                >
                    <button
                        type="button"
                        class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded border transition"
                        :class="
                            isAlreadyAdded(product)
                                ? 'border-primary/40 bg-primary/10 text-primary'
                                : isSelected(product.id)
                                  ? 'border-primary bg-primary text-primary-foreground'
                                  : 'border-border bg-background text-muted-foreground hover:border-primary/50 hover:text-primary'
                        "
                        :disabled="isAlreadyAdded(product)"
                        :aria-label="
                            isAlreadyAdded(product)
                                ? t(
                                      'planogram-templates.product_search.already_added',
                                  )
                                : isSelected(product.id)
                                  ? 'Remover produto da seleção'
                                  : 'Adicionar produto à seleção'
                        "
                        @click.prevent="toggleProduct(product.id)"
                    >
                        <CheckCircle2
                            v-if="isAlreadyAdded(product)"
                            class="size-3.5"
                        />
                        <Check
                            v-else-if="isSelected(product.id)"
                            class="size-3"
                        />
                        <Plus v-else class="size-3" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 items-start gap-2">
                            <p
                                class="min-w-0 flex-1 truncate text-sm leading-5 font-medium"
                            >
                                {{ product.name }}
                            </p>
                            <Badge
                                v-if="isAlreadyAdded(product)"
                                variant="secondary"
                                class="shrink-0 text-[10px]"
                            >
                                {{
                                    t(
                                        'planogram-templates.product_search.already_added',
                                    )
                                }}
                            </Badge>
                        </div>
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ product.ean }}
                        </p>
                        <Badge variant="secondary" class="mt-0.5 text-[10px]">{{
                            product.brand
                        }}</Badge>
                    </div>
                </label>
            </div>
        </div>

        <!-- Add button -->
        <div class="shrink-0 border-t border-border bg-card pt-3">
            <Button
                :disabled="selectedIds.length === 0 || !selectedGroupingName"
                class="box-border h-9 w-full max-w-full disabled:border-border disabled:bg-muted/50 disabled:text-foreground/60 disabled:opacity-100"
                @click="addSelected"
            >
                {{ t('planogram-templates.product_search.add_button') }}
                {{
                    selectedIds.length > 0
                        ? `${selectedIds.length} ${selectedIds.length === 1 ? t('planogram-templates.product_search.product_singular') : t('planogram-templates.product_search.product_plural')}`
                        : t('planogram-templates.product_search.selected')
                }}
                →
            </Button>
        </div>
    </div>
</template>
