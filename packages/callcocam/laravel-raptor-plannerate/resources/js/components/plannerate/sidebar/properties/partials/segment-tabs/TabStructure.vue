<template>
    <div class="space-y-3">
        <p class="text-xs font-semibold text-foreground">
            {{ t('plannerate.sidebar.segment_details.structure.title') }}
        </p>

        <!-- Sem categoria vinculada -->
        <div v-if="!categoryName" class="rounded-md border bg-muted/30 p-4 text-center">
            <p class="text-sm text-muted-foreground">
                {{ t('plannerate.sidebar.segment_details.structure.no_category') }}
            </p>
        </div>

        <!-- Hierarquia mercadológica -->
        <div v-else class="space-y-1.5">
            <div
                v-for="level in hierarchyLevels"
                :key="level.key"
                class="flex items-start justify-between rounded-md px-3 py-1.5"
                :class="level.value ? 'bg-muted/40' : 'bg-muted/10'"
            >
                <div class="flex items-center gap-2">
                    <!-- Indicador de nível de hierarquia -->
                    <div
                        class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full"
                        :class="level.value ? 'bg-primary' : 'bg-muted-foreground/30'"
                    />
                    <span class="text-xs text-muted-foreground">{{ level.label }}</span>
                </div>
                <span
                    class="ml-2 text-right text-xs font-medium"
                    :class="level.value ? 'text-foreground' : 'text-muted-foreground/50'"
                >
                    {{ level.value || '—' }}
                </span>
            </div>
        </div>

        <!-- Caminho completo (se disponível) -->
        <div v-if="product?.category_full_path" class="rounded-md border bg-primary/5 px-3 py-2">
            <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                Caminho completo
            </p>
            <p class="mt-1 text-xs text-foreground">{{ product.category_full_path }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';

interface Props {
    /** Produto do segmento */
    product?: Product | null;
}

const props = defineProps<Props>();
const { t } = useT();

/**
 * Nome da categoria folha do produto (carregada via relação eager).
 * A relação `category` é eager-loaded no GondolaController com id, name, category_id.
 */
const categoryName = computed(() => {
    const cat = props.product?.category;
    if (!cat) return null;
    if (typeof cat === 'string') return cat;
    return cat.name ?? null;
});

/**
 * Hierarquia mercadológica derivada do category_full_path (se disponível)
 * ou mostrando apenas a categoria folha carregada pela relação.
 *
 * O category_full_path é desabilitado pelo GondolaController (setAppends([]))
 * para performance, portanto a hierarquia completa pode não estar disponível —
 * nesse caso exibe o nome da categoria na posição mais próxima da raiz.
 */
const hierarchyLevels = computed(() => {
    const levels = [
        { key: 'retail_segment', label: t('plannerate.sidebar.segment_details.structure.retail_segment'), value: '' },
        { key: 'department', label: t('plannerate.sidebar.segment_details.structure.department'), value: '' },
        { key: 'subdepartment', label: t('plannerate.sidebar.segment_details.structure.subdepartment'), value: '' },
        { key: 'category', label: t('plannerate.sidebar.segment_details.structure.category'), value: '' },
        { key: 'subcategory', label: t('plannerate.sidebar.segment_details.structure.subcategory'), value: '' },
        { key: 'segment', label: t('plannerate.sidebar.segment_details.structure.segment'), value: '' },
        { key: 'subsegment', label: t('plannerate.sidebar.segment_details.structure.subsegment'), value: '' },
    ];

    const fullPath = props.product?.category_full_path;

    if (fullPath) {
        // Preenche os níveis a partir da esquerda (raiz → folha)
        const parts = fullPath.split(' > ').map((s: string) => s.trim());
        parts.forEach((part: string, index: number) => {
            if (index < levels.length) {
                levels[index].value = part;
            }
        });
    } else if (categoryName.value) {
        // Sem caminho completo: mostra apenas a categoria carregada na posição de Categoria (nível 4)
        levels[3].value = categoryName.value;
    }

    return levels;
});
</script>
