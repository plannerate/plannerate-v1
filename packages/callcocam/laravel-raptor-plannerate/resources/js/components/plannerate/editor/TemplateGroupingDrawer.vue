<script setup lang="ts">
import { Loader2, Tags } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAutoplanogramUrls } from '@/composables/useAutoplanogramUrls';

interface TemplateGrouping {
    grouping: string;
    grouping_normalized: string;
    slots_count: number;
    modules: number[];
    shelves: number[];
}

const props = defineProps<{
    gondolaId: string;
    templateId: string | null;
}>();

const emit = defineEmits<{
    'update:selectedGroupingNormalized': [value: string | null];
}>();

const { templateGroupingsUrl } = useAutoplanogramUrls(props.gondolaId);

const isLoading = ref(false);
const groupings = ref<TemplateGrouping[]>([]);
const selectedGroupingNormalized = ref<string | null>(null);

const hasTemplate = computed(() => !!props.templateId);

async function loadGroupings(): Promise<void> {
    if (!hasTemplate.value) {
        groupings.value = [];
        selectedGroupingNormalized.value = null;
        emit('update:selectedGroupingNormalized', null);

        return;
    }

    isLoading.value = true;

    try {
        const res = await fetch(templateGroupingsUrl());
        if (!res.ok) {
            throw new Error('request_failed');
        }

        const json = await res.json();
        groupings.value = Array.isArray(json.data) ? json.data : [];

        const selectedStillExists = groupings.value.some(
            (grouping) => grouping.grouping_normalized === selectedGroupingNormalized.value,
        );

        if (!selectedStillExists) {
            selectedGroupingNormalized.value = null;
            emit('update:selectedGroupingNormalized', null);
        }
    } catch {
        toast.error('Nao foi possivel carregar os groupings do template.');
    } finally {
        isLoading.value = false;
    }
}

function selectGrouping(groupingNormalized: string): void {
    selectedGroupingNormalized.value = groupingNormalized;
    emit('update:selectedGroupingNormalized', groupingNormalized);
}

function clearSelection(): void {
    selectedGroupingNormalized.value = null;
    emit('update:selectedGroupingNormalized', null);
}

watch(
    () => props.templateId,
    () => {
        void loadGroupings();
    },
);

onMounted(() => {
    void loadGroupings();
});
</script>

<template>
    <div class="absolute right-4 top-4 z-40 w-80 rounded-lg border border-border bg-background/95 shadow-sm backdrop-blur">
        <div class="flex items-center justify-between border-b border-border px-3 py-2">
            <div class="flex items-center gap-2 text-sm font-medium">
                <Tags class="size-4 text-muted-foreground" />
                <span>Groupings do template</span>
                <Loader2 v-if="isLoading" class="size-3.5 animate-spin text-muted-foreground" />
            </div>
            <Button
                v-if="selectedGroupingNormalized"
                variant="ghost"
                size="sm"
                class="h-7 px-2 text-xs"
                @click="clearSelection"
            >
                Limpar
            </Button>
        </div>

        <div v-if="!hasTemplate" class="px-3 py-4 text-xs text-muted-foreground">
            Esta gondola ainda nao possui template associado.
        </div>

        <div v-else-if="!isLoading && groupings.length === 0" class="px-3 py-4 text-xs text-muted-foreground">
            Nenhum grouping encontrado para o template desta gondola.
        </div>

        <div v-else class="max-h-72 space-y-1 overflow-y-auto p-2">
            <button
                v-for="grouping in groupings"
                :key="grouping.grouping_normalized"
                type="button"
                class="w-full rounded-md border px-2 py-2 text-left transition-colors"
                :class="selectedGroupingNormalized === grouping.grouping_normalized
                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30'
                    : 'border-transparent hover:border-border hover:bg-muted/60'"
                @click="selectGrouping(grouping.grouping_normalized)"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="line-clamp-2 text-sm font-medium">{{ grouping.grouping }}</p>
                    <Badge variant="secondary" class="h-5 px-1.5 text-[10px]">
                        {{ grouping.slots_count }} slots
                    </Badge>
                </div>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    Modulos: {{ grouping.modules.join(', ') }} • Prateleiras: {{ grouping.shelves.join(', ') }}
                </p>
            </button>
        </div>
    </div>
</template>
