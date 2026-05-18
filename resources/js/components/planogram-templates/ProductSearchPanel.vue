<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ProductSearchResult } from './types';

const props = defineProps<{
    searchResults: ProductSearchResult[];
    searching: boolean;
    availableGroupings: string[];
}>();

const emit = defineEmits<{
    search: [query: string];
    'add-products': [items: Array<{ product: ProductSearchResult; grouping: string }>];
}>();

const query = ref('');
const targetGrouping = ref('');
const selectedIds = ref<string[]>([]);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (value) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => emit('search', value.trim()), 350);
});

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
    if (!targetGrouping.value || selectedIds.value.length === 0) return;
    const idSet = new Set(selectedIds.value);
    const items = props.searchResults
        .filter((p) => idSet.has(p.id))
        .map((product) => ({ product, grouping: targetGrouping.value }));
    emit('add-products', items);
    selectedIds.value = [];
}
</script>

<template>
    <div class="flex h-full flex-col gap-3">
        <div class="grid gap-2">
            <!-- Search input -->
            <div class="relative">
                <Search class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="query"
                    class="pl-8"
                    placeholder="Buscar por EAN, nome ou marca..."
                />
            </div>

            <!-- Grouping selector -->
            <Select v-model="targetGrouping" :disabled="availableGroupings.length === 0">
                <SelectTrigger>
                    <SelectValue placeholder="Grouping de destino" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="g in availableGroupings" :key="g" :value="g">
                        {{ g }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="availableGroupings.length === 0" class="text-xs text-muted-foreground">
                Configure os slots (etapa 2) primeiro
            </p>
        </div>

        <!-- Results list -->
        <div class="flex-1 overflow-y-auto">
            <div v-if="searching" class="py-6 text-center text-sm text-muted-foreground">
                Buscando...
            </div>
            <div v-else-if="query && searchResults.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                Nenhum produto encontrado
            </div>
            <div v-else-if="!query" class="py-6 text-center text-sm text-muted-foreground">
                Digite para buscar produtos
            </div>
            <div v-else class="divide-y divide-border rounded-md border border-border">
                <label
                    v-for="product in searchResults"
                    :key="product.id"
                    class="flex cursor-pointer items-start gap-3 px-3 py-2.5 transition hover:bg-muted/30"
                    :class="{ 'bg-primary/5': isSelected(product.id) }"
                >
                    <Checkbox
                        :id="`product-${product.id}`"
                        :checked="isSelected(product.id)"
                        class="mt-0.5 shrink-0"
                        @update:checked="toggleProduct(product.id)"
                    />
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
            :disabled="selectedIds.length === 0 || !targetGrouping"
            class="w-full"
            @click="addSelected"
        >
            Adicionar {{ selectedIds.length > 0 ? `${selectedIds.length} produto(s)` : 'selecionados' }} →
        </Button>
    </div>
</template>
