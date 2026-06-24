<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Box class="mr-2 inline size-5 text-foreground" />
                {{ t('plannerate.sidebar.section_details.section') }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ section.name.toString().replace('Sessão', 'Módulo') }}
            </p>
        </div>

        <Separator />

        <div class="space-y-3">
            <div class="space-y-2">
                <Label for="section-code">{{ t('plannerate.sidebar.section_details.code') }}</Label>
                <Input
                    id="section-code"
                    :model-value="section.code"
                    @update:model-value="handleUpdate('code', $event)"
                />
            </div>

            <div class="space-y-2">
                <Label for="section-name">{{ t('plannerate.print.product_detail.name') }}</Label>
                <Input
                    id="section-name"
                    :model-value="section.name.toString().replace('Sessão', 'Módulo')"
                    @update:model-value="handleUpdate('name', $event)"
                />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="section-height">{{ t('plannerate.print.product_detail.height') }} (cm)</Label>
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
                <div class="space-y-2">
                    <Label for="section-width">{{ t('plannerate.print.product_detail.width') }} (cm)</Label>
                    <Input
                        id="section-width"
                        :model-value="section.width"
                        @update:model-value="handleUpdateWidth(Number($event))"
                        type="number"
                        step="0.1"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="base-height">{{ t('plannerate.sidebar.section_details.base_height') }}</Label>
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
                    <Label for="base-depth">{{ t('plannerate.sidebar.section_details.base_depth') }}</Label>
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
                        >{{ t('plannerate.sidebar.section_details.rack_width') }}</Label
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
                <h4 class="mb-2 font-medium">{{ t('plannerate.sidebar.section_details.holes_config') }}</h4>
                <div class="grid grid-cols-3 gap-2">
                    <div class="space-y-2">
                        <Label for="hole-height">{{ t('plannerate.sidebar.section_details.hole_height') }}</Label>
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
                        <Label for="hole-width">{{ t('plannerate.sidebar.section_details.hole_width') }}</Label>
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
                    <div class="space-y-2">
                        <Label for="hole-spacing">{{ t('plannerate.sidebar.section_details.hole_spacing') }}</Label>
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
                </div>
            </div>

            <Separator />

            <!-- Botões de ação -->
            <div class="space-y-2">
                <Label>{{ t('plannerate.sidebar.section_details.actions') }}</Label>
                <div class="grid grid-cols-2 gap-2">
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleMoveLeft"
                        :disabled="!sectionActions.canMoveLeft"
                        :tooltip="t('plannerate.sidebar.section_details.move_left_tooltip')"
                    >
                        <ArrowLeft class="mr-2 size-4" />
                        {{ t('plannerate.sidebar.section_details.move') }}
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleMoveRight"
                        :disabled="!sectionActions.canMoveRight"
                        :tooltip="t('plannerate.sidebar.section_details.move_right_tooltip')"
                    >
                        <ArrowRight class="mr-2 size-4" />
                        {{ t('plannerate.sidebar.section_details.move') }}
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleInvertShelves"
                        :disabled="!sectionActions.canInvertShelves"
                        class="col-span-2"
                        :tooltip="t('plannerate.sidebar.section_details.invert_shelves_tooltip')"
                    >
                        <ArrowUpDown class="mr-2 size-4" />
                        {{ t('plannerate.sidebar.section_details.invert_shelves') }}
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="outline"
                        size="sm"
                        @click="handleApplyToAll"
                        :disabled="otherSections.length === 0"
                        class="col-span-2"
                        :tooltip="t('plannerate.sidebar.section_details.apply_to_all_tooltip')"
                    >
                        <CopyCheck class="mr-2 size-4" />
                        {{ t('plannerate.sidebar.section_details.apply_to_all') }}
                    </ButtonWithTooltip>
                    <ButtonWithTooltip
                        variant="destructive"
                        size="sm"
                        @click="handleDelete"
                        class="col-span-2"
                        :tooltip="t('plannerate.sidebar.section_details.delete_tooltip')"
                    >
                        <Trash2 class="mr-2 size-4" />
                        {{ t('plannerate.sidebar.section_details.delete') }}
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
    CopyCheck,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramKeyboard } from '@/composables/plannerate/interactions/usePlanogramKeyboard';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { useSectionActions } from '@/composables/plannerate/actions/useSectionActions';
import { useT } from '@/composables/useT';
import type { Section } from '@/types/planogram';

interface Props {
    item: Section;
}

const props = defineProps<Props>();

// Busca sempre o valor mais atualizado do editor para garantir reatividade
const section = computed(() => {
    const found = editor.findSectionById(props.item.id);
    return found || props.item;
});
const { t } = useT();
const selection = usePlanogramSelection();
const keyboard = usePlanogramKeyboard();

// Usa composable compartilhado para ações de seção
// Passa função getter para o composable trabalhar com computed
const sectionActions = useSectionActions(() => section.value);

const editor = usePlanogramEditor();

/** Demais seções da gôndola (excluindo a atual e deletadas) */
const otherSections = computed(() =>
    editor.sectionsOrdered.value.filter((s) => s.id !== section.value.id),
);

/**
 * Atualiza largura e largura da base juntas (campo único)
 */
function handleUpdateWidth(value: number) {
    if (!section.value?.id) {
        return;
    }

    editor.updateSection(section.value.id, { width: value, base_width: value });
}

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
 * Aplica as dimensões do módulo atual a todos os demais módulos da gôndola.
 * Campos copiados: dimensões físicas e configuração de furos.
 * Campos excluídos: code, name, ordering (únicos por módulo).
 */
function handleApplyToAll() {
    const targets = otherSections.value;

    if (targets.length === 0) {
        return;
    }

    // Copia todas as dimensões físicas — name e code são únicos por módulo
    const dimensionFields = {
        height: section.value.height,
        width: section.value.width,
        base_height: section.value.base_height,
        base_width: section.value.base_width,
        base_depth: section.value.base_depth,
        cremalheira_width: section.value.cremalheira_width,
        hole_height: section.value.hole_height,
        hole_width: section.value.hole_width,
        hole_spacing: section.value.hole_spacing,
    };

    targets.forEach((s) => editor.updateSection(s.id, dimensionFields));

    toast.success(
        t('plannerate.sidebar.section_details.apply_to_all_success', {
            count: targets.length,
        }),
    );
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
