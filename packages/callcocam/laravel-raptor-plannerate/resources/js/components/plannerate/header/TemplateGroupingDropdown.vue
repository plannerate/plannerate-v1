<script setup lang="ts">
import { Check, ChevronsUpDown, Search, Tags, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    currentGondola,
    selectedTemplateCategoryId,
} from '@/composables/plannerate/core/useGondolaState';

interface TemplateGrouping {
    category_id: string;
    name: string;
    slots_count: number;
    modules: number[];
    shelves: number[];
}

const searchQuery = ref('');

const hasTemplate = computed(() => !!currentGondola.value?.template_id);

const groupings = computed<TemplateGrouping[]>(() => {
    const gondola = currentGondola.value;
    if (!gondola?.sections) {
        return [];
    }

    const byCategory = new Map<
        string,
        { name: string; modules: Set<number>; shelves: Set<number>; count: number }
    >();

    for (const section of gondola.sections) {
        for (const shelf of section.shelves ?? []) {
            const slot = shelf.template_slot;
            if (!slot?.category_id) {
                continue;
            }

            const entry = byCategory.get(slot.category_id) ?? {
                name: slot.category_name ?? slot.category_id,
                modules: new Set<number>(),
                shelves: new Set<number>(),
                count: 0,
            };

            entry.modules.add(slot.module_number);
            entry.shelves.add(slot.shelf_order);
            entry.count++;
            byCategory.set(slot.category_id, entry);
        }
    }

    return [...byCategory.entries()]
        .map(([categoryId, data]) => ({
            category_id: categoryId,
            name: data.name,
            slots_count: data.count,
            modules: [...data.modules].sort((a, b) => a - b),
            shelves: [...data.shelves].sort((a, b) => a - b),
        }))
        .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
});

const selectedGrouping = computed(() => {
    return (
        groupings.value.find(
            (g) => g.category_id === selectedTemplateCategoryId.value,
        ) ?? null
    );
});

const filteredGroupings = computed(() => {
    const normalizedSearch = searchQuery.value.trim().toLocaleLowerCase('pt-BR');

    if (!normalizedSearch) {
        return groupings.value;
    }

    return groupings.value.filter((item) => {
        return item.name.toLocaleLowerCase('pt-BR').includes(normalizedSearch);
    });
});

const buttonLabel = computed(() => {
    if (!hasTemplate.value) {
        return 'Categorias';
    }

    return selectedGrouping.value ? selectedGrouping.value.name : 'Selecionar categoria';
});

watch(
    () => hasTemplate.value,
    (value) => {
        if (!value) {
            searchQuery.value = '';
            selectedTemplateCategoryId.value = null;
        }
    },
);

watch(groupings, (newGroupings) => {
    const stillValid = newGroupings.some(
        (g) => g.category_id === selectedTemplateCategoryId.value,
    );

    if (!stillValid) {
        selectedTemplateCategoryId.value = null;
    }
});
</script>

<template>
    <DropdownMenu>
        <div class="flex items-center gap-1">
            <DropdownMenuTrigger as-child>
                <Button variant="outline" size="sm" class="h-8 max-w-72 justify-between gap-2" :disabled="!hasTemplate">
                    <span class="flex min-w-0 items-center gap-2">
                        <Tags class="size-4 shrink-0" />
                        <span class="truncate">{{ buttonLabel }}</span>
                    </span>
                    <span class="flex items-center gap-1.5 shrink-0">
                        <Badge v-if="groupings.length > 0" variant="secondary" class="h-5 px-1.5 text-[10px]">
                            {{ groupings.length }}
                        </Badge>
                        <ChevronsUpDown class="size-3.5 text-muted-foreground" />
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <Button
                v-if="selectedTemplateCategoryId"
                variant="ghost"
                size="icon"
                class="size-8 shrink-0"
                title="Limpar seleção de categoria"
                @click="selectedTemplateCategoryId = null"
            >
                <X class="size-3.5" />
            </Button>
        </div>

        <DropdownMenuContent align="start" class="z-9999 w-120">

            <div class="px-2 py-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar categoria..." class="h-8 pl-9" />
                </div>
            </div>

            <DropdownMenuSeparator />

            <DropdownMenuItem
                v-for="grouping in filteredGroupings"
                :key="grouping.category_id"
                class="cursor-pointer"
                :class="selectedTemplateCategoryId === grouping.category_id ? 'bg-muted/70' : ''"
                @click="selectedTemplateCategoryId = grouping.category_id">
                <div class="flex w-full items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ grouping.name }}</p>
                        <p class="text-[11px] text-muted-foreground">
                            Módulos: {{ grouping.modules.join(', ') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        <Check v-if="selectedTemplateCategoryId === grouping.category_id"
                            class="size-4 text-primary" />
                        <Badge variant="secondary" class="h-5 px-1.5 text-[10px]">
                            {{ grouping.slots_count }}
                        </Badge>
                    </div>
                </div>
            </DropdownMenuItem>

            <DropdownMenuItem v-if="filteredGroupings.length === 0" disabled>
                Nenhuma categoria encontrada
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
