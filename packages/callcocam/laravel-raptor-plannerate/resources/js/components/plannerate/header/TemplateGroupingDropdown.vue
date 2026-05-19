<script setup lang="ts">
import { Check, ChevronsUpDown, Loader2, Search, Tags } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    selectedTemplateGroupingNormalized,
} from '@/composables/plannerate/editor/useGondolaState';

interface TemplateGrouping {
    grouping: string;
    grouping_normalized: string;
    slots_count: number;
    modules: number[];
    shelves: number[];
}

const props = defineProps<{
    gondolaId: string | null;
    templateId: string | null;
}>();

const groupings = ref<TemplateGrouping[]>([]);
const isLoading = ref(false);
const searchQuery = ref('');
const page = usePage<{ subdomain?: string }>();

const hasTemplate = computed(() => !!props.templateId && !!props.gondolaId);

function stripDomain(url: string): string {
    return url.replace(/^\/\/[^/]+/, '');
}

function templateGroupingsUrl(): string {
    const subdomain = page.props.subdomain?.toString().trim()
        || (typeof window !== 'undefined' ? window.location.hostname.split('.')[0] || '' : '');
    const gondolaId = props.gondolaId ?? '';

    return stripDomain(
        `//${subdomain}.plannerate.localhost/api/gondolas/${gondolaId}/template-groupings`,
    );
}

const selectedGrouping = computed(() => {
    return (
        groupings.value.find(
            (g) => g.grouping_normalized === selectedTemplateGroupingNormalized.value,
        ) ?? null
    );
});

const filteredGroupings = computed(() => {
    const normalizedSearch = searchQuery.value.trim().toLocaleLowerCase('pt-BR');

    if (!normalizedSearch) {
        return groupings.value;
    }

    return groupings.value.filter((item) => {
        const haystack = `${item.grouping} ${item.grouping_normalized}`.toLocaleLowerCase('pt-BR');

        return haystack.includes(normalizedSearch);
    });
});

function groupingTail(grouping: string): string {
    const parts = grouping
        .split('|')
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length === 0) {
        return grouping;
    }

    return parts[parts.length - 1] ?? grouping;
}

function groupingHead(grouping: string): string {
    const parts = grouping
        .split('|')
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length <= 1) {
        return '';
    }

    return parts.slice(0, -1).join(' | ');
}

const buttonLabel = computed(() => {
    if (!hasTemplate.value) {
        return 'Groupings';
    }

    return selectedGrouping.value ? groupingTail(selectedGrouping.value.grouping) : 'Selecionar grouping';
});

async function loadGroupings(): Promise<void> {
    if (!hasTemplate.value) {
        groupings.value = [];
        selectedTemplateGroupingNormalized.value = null;

        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch(templateGroupingsUrl());
        if (!response.ok) {
            throw new Error('request_failed');
        }

        const payload = await response.json();
        groupings.value = Array.isArray(payload.data) ? payload.data : [];

        const stillValidSelection = groupings.value.some(
            (item) => item.grouping_normalized === selectedTemplateGroupingNormalized.value,
        );

        if (!stillValidSelection) {
            selectedTemplateGroupingNormalized.value = null;
        }
    } catch {
        toast.error('Nao foi possivel carregar os groupings do template.');
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [props.gondolaId, props.templateId] as const,
    () => {
        void loadGroupings();
    },
);

onMounted(() => {
    void loadGroupings();
});

watch(
    () => hasTemplate.value,
    (value) => {
        if (!value) {
            searchQuery.value = '';
        }
    },
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="h-8 max-w-72 justify-between gap-2" :disabled="!hasTemplate">
                <span class="flex min-w-0 items-center gap-2">
                    <Tags class="size-4 shrink-0" />
                    <span class="truncate">{{ buttonLabel }}</span>
                </span>
                <span class="flex items-center gap-1.5 shrink-0">
                    <Loader2 v-if="isLoading" class="size-3.5 animate-spin text-muted-foreground" />
                    <Badge v-else-if="groupings.length > 0" variant="secondary" class="h-5 px-1.5 text-[10px]">
                        {{ groupings.length }}
                    </Badge>
                    <ChevronsUpDown class="size-3.5 text-muted-foreground" />
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="z-9999 w-120">

            <div class="px-2 py-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar grouping..." class="h-8 pl-9" />

                </div>
            </div>

            <DropdownMenuSeparator />

            <DropdownMenuItem v-for="grouping in filteredGroupings" :key="grouping.grouping_normalized"
                class="cursor-pointer"
                :class="selectedTemplateGroupingNormalized === grouping.grouping_normalized ? 'bg-muted/70' : ''"
                @click="selectedTemplateGroupingNormalized = grouping.grouping_normalized">
                <div class="flex w-full items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-end space-x-1 ">
                            <p class="truncate text-sm font-medium">{{ groupingTail(grouping.grouping) }}</p>
                            <p v-if="groupingHead(grouping.grouping)"
                                class="truncate text-[11px] text-muted-foreground">
                                {{ groupingHead(grouping.grouping) }}
                            </p>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Modulos: {{ grouping.modules.join(', ') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        <Check v-if="selectedTemplateGroupingNormalized === grouping.grouping_normalized"
                            class="size-4 text-primary" />
                        <Badge variant="secondary" class="h-5 px-1.5 text-[10px]">
                            {{ grouping.slots_count }}
                        </Badge>
                    </div>
                </div>
            </DropdownMenuItem>

            <DropdownMenuItem v-if="!isLoading && filteredGroupings.length === 0" disabled>
                Nenhum grouping encontrado
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
