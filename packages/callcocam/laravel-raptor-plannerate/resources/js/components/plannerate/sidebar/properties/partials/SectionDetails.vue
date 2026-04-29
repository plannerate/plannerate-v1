<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Box class="mr-2 inline size-5 text-foreground" />
                Seção
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ section.name }}
            </p>
        </div>

        <Separator />

        <div class="space-y-3">
            <div class="space-y-2">
                <Label for="section-name">Nome</Label>
                <Input
                    id="section-name"
                    :model-value="section.name"
                    @update:model-value="handleUpdate('name', $event)"
                />
            </div>

            <div class="space-y-2">
                <Label for="section-code">Código</Label>
                <Input
                    id="section-code"
                    :model-value="section.code"
                    @update:model-value="handleUpdate('code', $event)"
                />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="section-width">Largura (cm)</Label>
                    <Input
                        id="section-width"
                        :model-value="section.width"
                        @update:model-value="
                            handleUpdate('width', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="section-height">Altura (cm)</Label>
                    <Input
                        id="section-height"
                        :model-value="section.height"
                        @update:model-value="
                            handleUpdate('height', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="base-width">Base Largura (cm)</Label>
                    <Input
                        id="base-width"
                        :model-value="section.base_width"
                        @update:model-value="
                            handleUpdate('base_width', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="base-height">Base Altura (cm)</Label>
                    <Input
                        id="base-height"
                        :model-value="section.base_height"
                        @update:model-value="
                            handleUpdate('base_height', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="base-depth">Base Profundidade (cm)</Label>
                    <Input
                        id="base-depth"
                        :model-value="section.base_depth"
                        @update:model-value="
                            handleUpdate('base_depth', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="cremalheira-width"
                        >Larg. Cremalheira (cm)</Label
                    >
                    <Input
                        id="cremalheira-width"
                        :model-value="section.cremalheira_width"
                        @update:model-value="
                            handleUpdate('cremalheira_width', Number($event))
                        "
                        type="number"
                        step="0.01"
                    />
                </div>
            </div>

            <Separator />

            <div>
                <h4 class="mb-2 font-medium">Configuração dos Furos</h4>
                <div class="grid grid-cols-3 gap-2">
                    <div class="space-y-2">
                        <Label for="hole-height">Altura Furo (cm)</Label>
                        <Input
                            id="hole-height"
                            :model-value="section.hole_height"
                            @update:model-value="
                                handleUpdate('hole_height', Number($event))
                            "
                            type="number"
                            step="0.01"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="hole-spacing">Espaçamento (cm)</Label>
                        <Input
                            id="hole-spacing"
                            :model-value="section.hole_spacing"
                            @update:model-value="
                                handleUpdate('hole_spacing', Number($event))
                            "
                            type="number"
                            step="0.01"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="hole-width">Largura Furo (cm)</Label>
                        <Input
                            id="hole-width"
                            :model-value="section.hole_width"
                            @update:model-value="
                                handleUpdate('hole_width', Number($event))
                            "
                            type="number"
                            step="0.01"
                        />
                    </div>
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
                        @click="handleMoveLeft"
                        :disabled="!sectionActions.canMoveLeft"
                        tooltip="Mover para esquerda (Ctrl+ <-)"
                    >
                        <ArrowLeft class="mr-2 size-4" />
                        Mover
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleMoveRight"
                        :disabled="!sectionActions.canMoveRight"
                        tooltip="Mover para direita (Ctrl+ ->)"
                    >
                        <ArrowRight class="mr-2 size-4" />
                        Mover
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleInvertShelves"
                        :disabled="!sectionActions.canInvertShelves"
                        class="col-span-2"
                        tooltip="Inverter ordem das prateleiras (Ctrl+I)"
                    >
                        <ArrowUpDown class="mr-2 size-4" />
                        Inverter Prateleiras
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="destructive"
                        size="sm"
                        @click="handleDelete"
                        class="col-span-2"
                        tooltip="Excluir seção (Del)"
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
import {
    ArrowLeft,
    ArrowRight,
    ArrowUpDown,
    Box,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramKeyboard } from '@/composables/plannerate/usePlanogramKeyboard';
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import { useSectionActions } from '@/composables/plannerate/useSectionActions';
import type { Section } from '@/types/planogram';

interface Props {
    item: Section;
}

const props = defineProps<Props>();

const section = computed(() => props.item);
const selection = usePlanogramSelection();
const keyboard = usePlanogramKeyboard();

// Usa composable compartilhado para ações de seção
// Passa função getter para o composable trabalhar com computed
const sectionActions = useSectionActions(() => section.value);

const editor = usePlanogramEditor();

/**
 * Atualiza propriedade da seção de forma reativa
 */
function handleUpdate(field: keyof Section, value: any) {
    if (!section.value?.id) {
return;
}

    // Atualiza de forma reativa usando o editor
    editor.updateSection(section.value.id, { [field]: value });
}

/**
 * Inverte a ordem das prateleiras (usa composable compartilhado)
 */
function handleInvertShelves() {
    sectionActions.invertShelves();
}

/**
 * Move seção para esquerda (usa composable compartilhado)
 */
function handleMoveLeft() {
    sectionActions.moveLeft();
}

/**
 * Move seção para direita (usa composable compartilhado)
 */
function handleMoveRight() {
    sectionActions.moveRight();
}

/**
 * Exclui a seção (mesma lógica do Delete)
 * Verifica se deve mostrar modal de confirmação
 */
function handleDelete() {
    if (!section.value?.id) {
        return;
    }

    // Garante que a seção está selecionada antes de qualquer ação
    selection.selectItem('section', section.value.id, section.value);

    // Usa composable compartilhado para verificar se deve mostrar modal
    const shouldShowConfirm = sectionActions.shouldShowDeleteConfirm('section');

    if (shouldShowConfirm) {
        // Abre modal de confirmação
        // O handleDeleteConfirm já garante que o item está selecionado antes de deletar
        keyboard.itemToDelete.value = {
            type: 'section',
            item: section.value,
        };
        keyboard.showDeleteConfirmDialog.value = true;
    } else {
        // Deleta diretamente sem confirmação
        // A seção já está selecionada acima
        selection.deleteSelected();
    }
}
</script>
