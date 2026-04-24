<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Box class="mr-2 inline size-5 text-foreground" />
                Prateleira
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ shelf.code || `Prateleira #${shelf.ordering}` }}
            </p>
        </div>

        <Separator />

        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="shelf-code">Código</Label>
                    <Input
                        id="shelf-code"
                        :model-value="shelf.code"
                        @update:model-value="handleUpdate('code', $event)"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="shelf-product-type">Tipo</Label>
                    <select
                        id="shelf-product-type"
                        :value="
                            shelf.product_type === 'hook' ? 'hook' : 'normal'
                        "
                        @change="handleProductTypeChange"
                        class="flex h-9 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="normal">Normal</option>
                        <option value="hook">Gancheira</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="shelf-width">Largura (cm)</Label>
                    <Input
                        id="shelf-width"
                        :model-value="shelf.shelf_width"
                        @update:model-value="
                            handleUpdate('shelf_width', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="shelf-height">Altura (cm)</Label>
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
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="shelf-depth">Profundidade (cm)</Label>
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
                <div class="space-y-2">
                    <Label for="shelf-position">Posição (cm)</Label>
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

            <Separator />

            <!-- Botões de ação -->
            <div class="space-y-2">
                <Label>Ações</Label>
                <div class="grid grid-cols-2 gap-2">
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleMoveUp"
                        :disabled="!shelfActions.canMoveUp"
                        tooltip="Mover para cima (Ctrl+ ↑)"
                    >
                        <ArrowUp class="mr-2 size-4" />
                        Cima
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleMoveDown"
                        :disabled="!shelfActions.canMoveDown"
                        tooltip="Mover para baixo (Ctrl+ ↓)"
                    >
                        <ArrowDown class="mr-2 size-4" />
                        Baixo
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleMoveLeft"
                        :disabled="!shelfActions.canMoveLeft"
                        tooltip="Mover para seção esquerda (Ctrl+ ←)"
                    >
                        <ArrowLeft class="mr-2 size-4" />
                        Esquerda
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleMoveRight"
                        :disabled="!shelfActions.canMoveRight"
                        tooltip="Mover para seção direita (Ctrl+ →)"
                    >
                        <ArrowRight class="mr-2 size-4" />
                        Direita
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleInvertSegments"
                        :disabled="!canInvertSegments"
                        class="col-span-2"
                        tooltip="Inverter ordem dos produtos (Ctrl+I)"
                    >
                        <ArrowUpDown class="mr-2 size-4" />
                        Inverter Produtos
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="destructive"
                        size="sm"
                        @click="handleDelete"
                        class="col-span-2"
                        tooltip="Excluir prateleira (Del)"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Excluir
                    </ButtonWithTooltip>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramKeyboard } from '@/composables/plannerate/usePlanogramKeyboard';
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import { findNearestHole } from '@/composables/plannerate/useSectionHoles';
import { useShelfActions } from '@/composables/plannerate/useShelfActions';
import type { Shelf } from '@/types/planogram';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    ArrowUpDown,
    Box,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    item: Shelf;
}

const props = defineProps<Props>();

const editor = usePlanogramEditor();
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
 * Verifica se pode inverter segments (precisa de pelo menos 2 segments)
 */
const canInvertSegments = computed(() => {
    const currentShelf = shelf.value;
    if (!currentShelf?.segments) return false;
    const activeSegments = currentShelf.segments.filter(
        (s: any) => !s.deleted_at,
    );
    return activeSegments.length >= 2;
});

/**
 * Atualiza propriedade da prateleira de forma reativa
 * Se for shelf_position, encaixa no furo mais próximo
 */
function handleUpdate(field: keyof Shelf, value: any) {
    if (!shelf.value?.id) return;

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
    if (!target) return;
    handleUpdate('product_type', target.value);
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
    if (!shelf.value?.id) return;
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
</script>
