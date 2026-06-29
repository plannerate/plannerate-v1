<template>
    <div class="space-y-4">
        <!-- Cabeçalho: ícone da prateleira + código + botão fechar -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                >
                    <Box class="size-5" />
                </div>
                <div>
                    <h3 class="text-lg font-bold leading-tight text-foreground">
                        {{ t('plannerate.sidebar.shelf_details.shelf') }}
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        {{ shelf.code || `${t('plannerate.sidebar.shelf_details.shelf')} #${shelf.ordering}` }}
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="rounded-lg border border-border p-2 text-muted-foreground transition-colors hover:bg-accent"
                @click="handleClose"
            >
                <X class="size-4" />
            </button>
        </div>

        <!-- Card: Informações da Prateleira -->
        <div class="space-y-3 rounded-xl border border-border bg-card p-4">
            <div class="flex items-center gap-2">
                <div
                    class="flex size-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                >
                    <Info class="size-4" />
                </div>
                <h4 class="font-semibold text-foreground">
                    {{ t('plannerate.sidebar.shelf_details.info_title') }}
                </h4>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-2">
                    <Label for="shelf-code">{{ t('plannerate.sidebar.section_details.code') }}</Label>
                    <Input
                        id="shelf-code"
                        :model-value="shelf.code"
                        @update:model-value="handleUpdate('code', $event)"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="shelf-product-type">{{ t('plannerate.sidebar.shelf_details.type') }}</Label>
                    <select
                        id="shelf-product-type"
                        :value="
                            shelf.product_type === 'hook' ? 'hook' : 'normal'
                        "
                        @change="handleProductTypeChange"
                        class="flex h-9 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="normal">{{ t('plannerate.sidebar.shelf_details.normal') }}</option>
                        <option value="hook">{{ t('plannerate.sidebar.shelf_details.hook') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Card: Configuração Física -->
        <div class="space-y-3 rounded-xl border border-border bg-card p-4">
            <div class="flex items-center gap-2">
                <div
                    class="flex size-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                >
                    <Pencil class="size-4" />
                </div>
                <h4 class="font-semibold text-foreground">
                    {{ t('plannerate.sidebar.shelf_details.physical_config_title') }}
                </h4>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-2">
                    <Label for="shelf-height">{{ t('plannerate.sidebar.shelf_details.height') }}</Label>
                    <Input
                        id="shelf-height"
                        :model-value="shelf.shelf_height"
                        @update:model-value="
                            handleUpdate('shelf_height', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="shelf-depth">{{ t('plannerate.sidebar.shelf_details.depth') }}</Label>
                    <Input
                        id="shelf-depth"
                        :model-value="shelf.shelf_depth"
                        @update:model-value="
                            handleUpdate('shelf_depth', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
            </div>

            <div class="space-y-2">
                <Label for="shelf-position">{{ t('plannerate.sidebar.shelf_details.position_cm') }}</Label>
                <Input
                    id="shelf-position"
                    :model-value="shelf.shelf_position"
                    @update:model-value="
                        handleUpdate('shelf_position', Number($event))
                    "
                    type="number"
                    step="0.1"
                />
            </div>
        </div>

        <div class="space-y-3">
            <div
                v-if="shelf.template_slot"
                class="space-y-3 rounded-lg border border-border/70 bg-muted/40 p-3"
            >
                <div class="flex items-center justify-between gap-3">
                    <Label class="text-sm font-semibold">{{ t('plannerate.sidebar.shelf_details.shelf') }} Template Slot</Label>
                    <span class="rounded-full bg-background px-2 py-1 text-[11px] text-muted-foreground">
                        M{{ shelf.template_slot.module_number }} • P{{ shelf.template_slot.shelf_order }}
                    </span>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-medium text-foreground">
                        {{ shelf.template_slot.category_name || shelf.template_slot.category_id || 'Sem categoria' }}
                    </p>
                    <p v-if="shelf.template_slot.subcategory" class="text-xs text-muted-foreground">
                        Subcategoria: {{ shelf.template_slot.subcategory }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-md bg-background/70 px-2 py-1.5 text-muted-foreground">
                        Facings: <span class="text-foreground">min {{ shelf.template_slot.min_facings ?? '-' }} / max {{ shelf.template_slot.max_facings ?? '-' }}</span>
                    </div>
                    <div class="rounded-md bg-background/70 px-2 py-1.5 text-muted-foreground">
                        Prioridade: <span class="text-foreground">{{ shelf.template_slot.priority ?? '-' }}</span>
                    </div>
                    <div class="rounded-md bg-background/70 px-2 py-1.5 text-muted-foreground">
                        Tamanho: <span class="text-foreground">{{ humanizeOrder(shelf.template_slot.size_order) }}</span>
                    </div>
                    <div class="rounded-md bg-background/70 px-2 py-1.5 text-muted-foreground">
                        Preço: <span class="text-foreground">{{ humanizeOrder(shelf.template_slot.price_order) }}</span>
                    </div>
                    <div class="rounded-md bg-background/70 px-2 py-1.5 text-muted-foreground">
                        Exposição marca: <span class="text-foreground">{{ humanizeExposure(shelf.template_slot.brand_exposure) }}</span>
                    </div>
                    <div class="rounded-md bg-background/70 px-2 py-1.5 text-muted-foreground">
                        Exposição sabor: <span class="text-foreground">{{ humanizeExposure(shelf.template_slot.flavor_exposure) }}</span>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Fallback: <span class="text-foreground">{{ humanizeFallback(shelf.template_slot.space_fallback) }}</span> •
                    Expansão: <span class="text-foreground">{{ humanizeFacingExpansion(shelf.template_slot.facing_expansion) }}</span> •
                    Estoque alvo: <span class="text-foreground">{{ shelf.template_slot.use_target_stock ? 'Sim' : 'Não' }}</span>
                </p>
            </div>

        </div>

        <!-- Card: Ações da Prateleira -->
        <div class="space-y-3 rounded-xl border border-border bg-card p-4">
            <div class="flex items-center gap-2">
                <div
                    class="flex size-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                >
                    <Zap class="size-4" />
                </div>
                <h4 class="font-semibold text-foreground">
                    {{ t('plannerate.sidebar.shelf_details.actions_title') }}
                </h4>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveUp"
                    :disabled="!shelfActions.canMoveUp"
                    :tooltip="t('plannerate.sidebar.shelf_details.move_up_tooltip')"
                >
                    <ArrowUp class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.up') }}
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveDown"
                    :disabled="!shelfActions.canMoveDown"
                    :tooltip="t('plannerate.sidebar.shelf_details.move_down_tooltip')"
                >
                    <ArrowDown class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.down') }}
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveLeft"
                    :disabled="!shelfActions.canMoveLeft"
                    :tooltip="t('plannerate.sidebar.shelf_details.move_left_tooltip')"
                >
                    <ArrowLeft class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.left') }}
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveRight"
                    :disabled="!shelfActions.canMoveRight"
                    :tooltip="t('plannerate.sidebar.shelf_details.move_right_tooltip')"
                >
                    <ArrowRight class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.right') }}
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleInvertSegments"
                    :disabled="!canInvertSegments"
                    class="col-span-2"
                    :tooltip="t('plannerate.sidebar.shelf_details.invert_products_tooltip')"
                >
                    <ArrowUpDown class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.invert_products') }}
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleApplyToAllShelves"
                    :disabled="otherShelves.length === 0"
                    class="col-span-2"
                    :tooltip="t('plannerate.sidebar.shelf_details.apply_to_all_tooltip')"
                >
                    <CopyCheck class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.apply_to_all') }}
                </ButtonWithTooltip>
            </div>
        </div>

        <!-- Botão de remoção (fora dos cards, destaque em vermelho) -->
        <ButtonWithTooltip
            variant="destructive"
            size="sm"
            @click="handleDelete"
            class="w-full"
            :tooltip="t('plannerate.sidebar.shelf_details.delete_tooltip')"
        >
            <Trash2 class="mr-2 size-4" />
            {{ t('plannerate.sidebar.shelf_details.remove') }}
        </ButtonWithTooltip>
    </div>
</template>

<script setup lang="ts">
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    ArrowUpDown,
    Box,
    CopyCheck,
    Info,
    Pencil,
    Trash2,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramKeyboard } from '@/composables/plannerate/interactions/usePlanogramKeyboard';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { selectedTemplateCategoryId } from '@/composables/plannerate/core/useGondolaState';
import { findNearestHole } from '@/composables/plannerate/geometry/useSectionHoles';
import { useShelfActions } from '@/composables/plannerate/actions/useShelfActions';
import { useT } from '@/composables/useT';
import type { Shelf } from '@/types/planogram';

interface Props {
    item: Shelf;
}

const props = defineProps<Props>();

const editor = usePlanogramEditor();
const { t } = useT();
const selection = usePlanogramSelection();
const keyboard = usePlanogramKeyboard();

// Busca sempre o valor mais atualizado do editor para garantir reatividade
const shelf = computed(() => {
    const found = editor.findShelfById(props.item.id);

    return found?.shelf || props.item;
});

// Busca a seção da prateleira
const section = computed(() => {
    const found = editor.findShelfById(shelf.value.id);

    return found?.section;
});

// Usa composable compartilhado para ações de prateleira
const shelfActions = useShelfActions(
    () => shelf.value,
    () => section.value,
);

/**
 * Sincroniza a categoria do template_slot da prateleira com o estado global
 * ao montar o painel e sempre que a prateleira selecionada mudar.
 * Isso garante que o CategoryConfigPanel realce o card correspondente.
 */
watch(
    () => (shelf.value as any).template_slot?.category_id as string | undefined,
    (categoryId) => {
        selectedTemplateCategoryId.value = categoryId ?? null;
    },
    { immediate: true },
);

/**
 * Verifica se pode inverter segments (precisa de pelo menos 2 segments)
 */
const canInvertSegments = computed(() => {
    const currentShelf = shelf.value;

    if (!currentShelf?.segments) {
return false;
}

    const activeSegments = currentShelf.segments.filter(
        (s: any) => !s.deleted_at,
    );

    return activeSegments.length >= 2;
});

/** Demais prateleiras do mesmo módulo (excluindo a atual e deletadas) */
const otherShelves = computed(() => {
    if (!section.value?.shelves) {
        return [];
    }

    return section.value.shelves.filter(
        (s: any) => !s.deleted_at && s.id !== shelf.value.id,
    );
});

/**
 * Aplica espessura, profundidade e tipo desta prateleira a todas as outras do módulo.
 * Largura é excluída pois segue a largura da seção.
 * Posição é excluída pois é única por prateleira.
 */
function handleApplyToAllShelves() {
    const targets = otherShelves.value;

    if (targets.length === 0) {
        return;
    }

    const fields = {
        shelf_height: shelf.value.shelf_height,
        shelf_depth: shelf.value.shelf_depth,
        product_type: shelf.value.product_type,
    };

    targets.forEach((s: any) => editor.updateShelf(s.id, fields));

    toast.success(
        t('plannerate.sidebar.shelf_details.apply_to_all_success', {
            count: targets.length,
        }),
    );
}

/**
 * Atualiza propriedade da prateleira de forma reativa
 * Se for shelf_position, encaixa no furo mais próximo
 */
function handleUpdate(field: keyof Shelf, value: any) {
    if (!shelf.value?.id) {
return;
}

    // Se estiver alterando a posição, encaixa no furo mais próximo
    if (field === 'shelf_position' && section.value) {
        const snappedPosition = findNearestHole(section.value, value);
        editor.updateShelf(shelf.value.id, { [field]: snappedPosition });

        return;
    }

    // Atualiza de forma reativa usando o editor
    editor.updateShelf(shelf.value.id, { [field]: value });
}

function handleProductTypeChange(event: Event) {
    const target = event.target as HTMLSelectElement | null;

    if (!target) {
return;
}

    handleUpdate('product_type', target.value);
}

/**
 * Fecha o painel de detalhes limpando a seleção atual
 */
function handleClose() {
    selection.clearSelection();
}

/**
 * Move prateleira para cima (usa composable compartilhado)
 */
function handleMoveUp() {
    shelfActions.moveUp();
}

/**
 * Move prateleira para baixo (usa composable compartilhado)
 */
function handleMoveDown() {
    shelfActions.moveDown();
}

/**
 * Move prateleira para seção esquerda (usa composable compartilhado)
 */
function handleMoveLeft() {
    shelfActions.moveLeft();
}

/**
 * Move prateleira para seção direita (usa composable compartilhado)
 */
function handleMoveRight() {
    shelfActions.moveRight();
}

/**
 * Inverte a ordem dos segments (produtos) da prateleira (mesma lógica do Ctrl+I)
 */
function handleInvertSegments() {
    if (!shelf.value?.id) {
return;
}

    editor.invertSegmentsOrder(shelf.value.id);
}

/**
 * Exclui a prateleira (mesma lógica do Delete)
 * Verifica se deve mostrar modal de confirmação
 */
function handleDelete() {
    if (!shelf.value?.id) {
        return;
    }

    // Garante que a prateleira está selecionada antes de qualquer ação
    if (section.value) {
        selection.selectItem('shelf', shelf.value.id, shelf.value, {
            section: section.value,
        });
    }

    // Usa composable compartilhado para verificar se deve mostrar modal
    const shouldShowConfirm = shelfActions.shouldShowDeleteConfirm('shelf');

    if (shouldShowConfirm) {
        // Abre modal de confirmação
        keyboard.itemToDelete.value = {
            type: 'shelf',
            item: shelf.value,
        };
        keyboard.showDeleteConfirmDialog.value = true;
    } else {
        // Deleta diretamente sem confirmação
        // A prateleira já está selecionada acima
        selection.deleteSelected();
    }
}

function humanizeOrder(value?: string | null): string {
    if (value === 'asc') {
        return 'Crescente';
    }
    if (value === 'desc') {
        return 'Decrescente';
    }

    return 'Nenhum';
}

function humanizeExposure(value?: string | null): string {
    if (value === 'vertical') {
        return 'Vertical';
    }
    if (value === 'horizontal') {
        return 'Horizontal';
    }

    return 'Mista';
}

function humanizeFallback(value?: string | null): string {
    if (value === 'reduce_facings') {
        return 'Reduzir facings';
    }
    if (value === 'reduce_c') {
        return 'Reduzir classe C';
    }

    return 'Pular';
}

function humanizeFacingExpansion(value?: string | null): string {
    if (value === 'score') {
        return 'Por score';
    }
    if (value === 'current_stock') {
        return 'Por estoque atual';
    }
    if (value === 'equal') {
        return 'Distribuição igual';
    }

    return 'Sem expansão';
}
</script>
