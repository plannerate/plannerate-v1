<script setup lang="ts">
import { Check, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/composables/useT';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { GroupingOption, ProductSearchResult } from './types';

const props = defineProps<{
    searchResults: ProductSearchResult[];
    searching: boolean;
    groupingOptions: GroupingOption[];
    selectedGroupingId?: string | null;
}>();

const emit = defineEmits<{
    search: [grouping: string | null];
    'add-products': [items: Array<{ product: ProductSearchResult; grouping: string }>];
}>();

const { t } = useT();

const allGroupingsValue = '__all_groupings__';
const query = ref('');
const targetGrouping = ref(props.selectedGroupingId ?? allGroupingsValue);
const selectedIds = ref<string[]>([]);

const selectedGroupingId = computed(() => (
    targetGrouping.value === allGroupingsValue ? '' : targetGrouping.value
));

const selectedGroupingName = computed(() => {
    if (selectedGroupingId.value === '') {
        return '';
    }

    return props.groupingOptions.find((option) => option.id === selectedGroupingId.value)?.name ?? '';
});

const filteredSearchResults = computed(() => {
    const eanQuery = query.value.trim();

    if (eanQuery === '') {
        return props.searchResults;
    }

    return props.searchResults.filter((product) => product.ean.includes(eanQuery));
});

watch(targetGrouping, () => {
    selectedIds.value = [];
    emit('search', selectedGroupingId.value || null);
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
        if (selectedGroupingId.value === '') {
            return;
        }

        if (!props.groupingOptions.some((option) => option.id === selectedGroupingId.value)) {
            targetGrouping.value = allGroupingsValue;
        }
    }
);

function toggleProduct(id: string): void {
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

function addSelected(): void {
    if (!selectedGroupingName.value || selectedIds.value.length === 0) return;
    const idSet = new Set(selectedIds.value);
    const items = filteredSearchResults.value
        .filter((p) => idSet.has(p.id))
        .map((product) => ({ product, grouping: selectedGroupingName.value }));
    emit('add-products', items);
    selectedIds.value = [];
}
</script>

<template>
    <div class="flex h-full min-w-0 flex-col gap-3">
        <div class="grid gap-2">
            <!-- Search input -->
            <div class="relative">
                <Search class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="query"
                    class="pl-8"
                    :placeholder="t('planogram-templates.product_search.search_placeholder')"
                />
            </div>

            <!-- Grouping selector -->
            <Select v-model="targetGrouping" :disabled="groupingOptions.length === 0">
                <SelectTrigger class="w-full min-w-0 max-w-full">
                    <SelectValue :placeholder="t('planogram-templates.product_search.grouping_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="allGroupingsValue">{{ t('planogram-templates.product_search.grouping_none') }}</SelectItem>
                    <SelectItem v-for="option in groupingOptions" :key="option.id" :value="option.id">
                        {{ option.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="groupingOptions.length === 0" class="text-xs text-muted-foreground">
                {{ t('planogram-templates.product_search.no_groupings_hint') }}
            </p>
        </div>

        <!-- Results list -->
        <div class="min-w-0 flex-1  max-h-[calc(100vh-8rem)] overflow-auto">
            <div v-if="searching" class="py-6 text-center text-sm text-muted-foreground">
                {{ t('planogram-templates.product_search.searching') }}
            </div>
            <div v-else-if="!selectedGroupingId" class="py-6 text-center text-sm text-muted-foreground">
                {{ t('planogram-templates.product_search.search_hint') }}
            </div>
            <div v-else-if="filteredSearchResults.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                {{ t('planogram-templates.product_search.no_results') }}
            </div>
            <div v-else class="divide-y divide-border rounded-md border border-border">
                <label
                    v-for="product in filteredSearchResults"
                    :key="product.id"
                    class="flex cursor-pointer items-start gap-3 px-3 py-2.5 transition hover:bg-muted/30"
                    :class="{ 'bg-primary/5': isSelected(product.id) }"
                >
                    <button
                        type="button"
                        class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded border transition"
                        :class="isSelected(product.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:border-primary/50 hover:text-primary'"
                        :aria-label="isSelected(product.id) ? 'Remover produto da seleção' : 'Adicionar produto à seleção'"
                        @click.prevent="toggleProduct(product.id)"
                    >
                        <Check v-if="isSelected(product.id)" class="size-3" />
                        <Plus v-else class="size-3" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ product.name }}</p>
                        <p class="font-mono text-xs text-muted-foreground">{{ product.ean }}</p>
                        <Badge variant="secondary" class="mt-0.5 text-[10px]">{{ product.brand }}</Badge>
                    </div>
                </label>
            </div>
        </div>

        <!-- Add button -->
        <Button
            :disabled="selectedIds.length === 0 || !selectedGroupingName"
            class="box-border w-full max-w-full shrink-0 disabled:opacity-100 disabled:border-border disabled:bg-muted/50 disabled:text-foreground/60"
            @click="addSelected"
        >
            {{ t('planogram-templates.product_search.add_button') }} {{ selectedIds.length > 0 ? `${selectedIds.length} ${selectedIds.length === 1 ? t('planogram-templates.product_search.product_singular') : t('planogram-templates.product_search.product_plural')}` : t('planogram-templates.product_search.selected') }} →
        </Button>
    </div>
</template>
