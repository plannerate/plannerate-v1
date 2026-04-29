<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { SlidersHorizontal, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

withDefaults(
    defineProps<{
        action: string;
        clearHref: string;
        searchName?: string;
        searchValue?: string;
        searchPlaceholder?: string;
        filterLabel?: string;
        clearLabel?: string;
        total?: number | null;
        totalLabel?: string;
        perPage?: number;
        perPageOptions?: number[];
    }>(),
    {
        searchName: 'search',
        searchValue: '',
        searchPlaceholder: 'Buscar...',
        filterLabel: 'Filtrar',
        clearLabel: 'Limpar filtros',
        total: null,
        totalLabel: undefined,
        perPage: 10,
        perPageOptions: () => [10, 25, 50, 100],
    },
);

const formRef = ref<HTMLFormElement | null>(null);

function sanitizeQueryParams(data: Record<string, string>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(data).filter(([, value]) => value.trim() !== ''),
    );
}

function submitForm(): void {
    if (!formRef.value) {
        return;
    }

    const rawData = Object.fromEntries(
        new FormData(formRef.value).entries(),
    ) as Record<string, string>;
    const data = sanitizeQueryParams(rawData);

    router.get(formRef.value.action, data, {
        preserveState: true,
        preserveScroll: true,
    });
}

function onSubmit(event: Event): void {
    event.preventDefault();
    submitForm();
}

const onDebouncedSearchInput = useDebounceFn(submitForm, 400);

defineExpose({ submitForm });

function onFormChange(event: Event): void {
    const target = event.target as HTMLElement;

    if (target.tagName === 'SELECT') {
        submitForm();
    }
}
</script>

<template>
    <form
        ref="formRef"
        :action="action"
        method="get"
        class="rounded-xl border border-border bg-card p-3"
        @submit.prevent="onSubmit"
        @change="onFormChange"
    >
        <div class="flex flex-wrap items-end gap-3">
            <!-- Search input -->
            <div class="relative min-w-48 flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    :name="searchName"
                    :default-value="searchValue"
                    :placeholder="searchPlaceholder"
                    class="h-9 w-full rounded-lg border-border bg-background pl-9 text-sm focus-visible:border-primary/60 focus-visible:ring-primary/20"
                    @input="onDebouncedSearchInput"
                />
            </div>

            <!-- Extra filter fields (selects, etc.) -->
            <slot />

            <div class="flex items-center gap-2">
                <span class="text-xs text-muted-foreground">Por página</span>
                <select
                    name="per_page"
                    :value="String(perPage)"
                    class="h-9 rounded-lg border border-border bg-background px-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option
                        v-for="option in perPageOptions"
                        :key="option"
                        :value="String(option)"
                    >
                        {{ option }}
                    </option>
                </select>
            </div>

            <!-- Filter submit button -->
            <Button
                type="submit"
                variant="outline"
                size="sm"
                class="h-9 gap-2 rounded-lg border-border"
            >
                <SlidersHorizontal class="size-4" />
                {{ filterLabel }}
            </Button>

            <!-- Clear filters link -->
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="h-9 rounded-lg text-muted-foreground hover:text-foreground"
            >
                <Link :href="clearHref">{{ clearLabel }}</Link>
            </Button>

            <!-- Optional total count -->
            <p
                v-if="total != null && total > 0"
                class="ml-auto shrink-0 text-sm text-muted-foreground"
            >
                Exibindo
                <span class="font-medium text-foreground">{{ total }}</span>
                <template v-if="totalLabel">
                    {{ total === 1 ? totalLabel : `${totalLabel}s` }}
                </template>
            </p>
        </div>
    </form>
</template>
