<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { SlidersHorizontal, Search } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
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
    }>(),
    {
        searchName: 'search',
        searchValue: '',
        searchPlaceholder: 'Buscar...',
        filterLabel: 'Filtrar',
        clearLabel: 'Limpar filtros',
        total: null,
        totalLabel: undefined,
    },
);

const formRef = ref<HTMLFormElement | null>(null);

function submitForm(): void {
    if (!formRef.value) return;
    const data = Object.fromEntries(
        new FormData(formRef.value).entries(),
    ) as Record<string, string>;
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
        <div class="flex flex-wrap items-center gap-3">
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
