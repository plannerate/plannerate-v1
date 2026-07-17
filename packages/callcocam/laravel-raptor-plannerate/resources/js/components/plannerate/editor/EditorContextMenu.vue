<template>
    <!--
        Menu de contexto GLOBAL do editor (um só para o canvas inteiro).
        O gatilho é um ponto invisível fixado nas coordenadas do clique — o
        DropdownMenu ancora o conteúdo nele (posicionamento/colisão/Escape/
        click-outside vêm de graça do reka-ui).
    -->
    <DropdownMenu :open="isContextMenuOpen" @update:open="handleOpenChange">
        <DropdownMenuTrigger as-child>
            <span
                aria-hidden="true"
                class="pointer-events-none fixed size-0"
                :style="{ left: `${contextMenuX}px`, top: `${contextMenuY}px` }"
            />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-60">
            <template v-for="(entry, index) in menuEntries" :key="index">
                <DropdownMenuSeparator v-if="entry.separator" />
                <DropdownMenuItem
                    v-else
                    :disabled="entry.disabled"
                    :variant="entry.danger ? 'destructive' : undefined"
                    @select="entry.run?.()"
                >
                    {{ entry.label }}
                    <DropdownMenuShortcut v-if="entry.shortcut">
                        {{ entry.shortcut }}
                    </DropdownMenuShortcut>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useT } from '@/composables/useT';
import { useSectionActions } from '../../../composables/plannerate/actions/useSectionActions';
import { useSegmentActions } from '../../../composables/plannerate/actions/useSegmentActions';
import { useShelfActions } from '../../../composables/plannerate/actions/useShelfActions';
import { copiedSegmentId } from '../../../composables/plannerate/core/useGondolaState';
import {
    findSectionById,
    findSegmentById,
    findShelfById,
} from '../../../composables/plannerate/core/useLookupHelpers';
import { usePlanogramEditor } from '../../../composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/core/usePlanogramSelection';
import { useEditorContextMenu } from '../../../composables/plannerate/interactions/useEditorContextMenu';
import { usePlanogramKeyboard } from '../../../composables/plannerate/interactions/usePlanogramKeyboard';
import { shouldShowDeleteConfirm } from '../../../composables/plannerate/shared/usePlanogramUtils';
import type { Section, Segment, Shelf } from '../../../types/planogram';

interface MenuEntry {
    separator?: boolean;
    label?: string;
    shortcut?: string;
    disabled?: boolean;
    danger?: boolean;
    run?: () => void;
}

const { t } = useT();
const editor = usePlanogramEditor();
const selection = usePlanogramSelection();
const {
    isContextMenuOpen,
    contextMenuX,
    contextMenuY,
    contextMenuTarget,
    closeContextMenu,
} = useEditorContextMenu();

// Refs singleton dos modais (duplicar seção / confirmar exclusão) — os
// dialogs são renderizados uma vez em PlanogramEditor.vue.
const {
    showDuplicateSectionDialog,
    sectionToDuplicate,
    showDeleteConfirmDialog,
    itemToDelete,
} = usePlanogramKeyboard();

function handleOpenChange(open: boolean): void {
    if (!open) {
        closeContextMenu();
    }
}

/**
 * Exclui respeitando o mesmo fluxo do teclado: modal de confirmação para
 * section/shelf (quando habilitada); segment deleta direto.
 */
function requestDelete(
    type: 'section' | 'shelf',
    item: Section | Shelf,
): void {
    if (shouldShowDeleteConfirm(type)) {
        itemToDelete.value = { type, item };
        showDeleteConfirmDialog.value = true;

        return;
    }

    selection.deleteSelected();
}

const menuEntries = computed<MenuEntry[]>(() => {
    const target = contextMenuTarget.value;

    if (!target) {
        return [];
    }

    if (target.type === 'segment') {
        const found = findSegmentById(target.id);

        if (!found) {
            return [];
        }

        const actions = useSegmentActions(
            () => found.segment as Segment,
            () => found.shelf as Shelf,
        );

        return [
            {
                label: t('plannerate.editor.context_menu.copy'),
                shortcut: 'Ctrl+C',
                run: () => {
                    copiedSegmentId.value = target.id;
                    toast.info(t('plannerate.editor.clipboard.segment_copied'));
                },
            },
            { separator: true },
            {
                label: t('plannerate.editor.context_menu.move_left'),
                shortcut: 'Ctrl+←',
                disabled: !actions.canMoveLeft.value,
                run: () => actions.moveLeft(),
            },
            {
                label: t('plannerate.editor.context_menu.move_right'),
                shortcut: 'Ctrl+→',
                disabled: !actions.canMoveRight.value,
                run: () => actions.moveRight(),
            },
            { separator: true },
            {
                label: t('plannerate.editor.context_menu.delete'),
                shortcut: 'Del',
                danger: true,
                run: () => selection.deleteSelected(),
            },
        ];
    }

    if (target.type === 'shelf') {
        const found = findShelfById(target.id);

        if (!found) {
            return [];
        }

        const actions = useShelfActions(
            () => found.shelf as Shelf,
            () => found.section as Section,
        );

        return [
            {
                label: t('plannerate.editor.context_menu.paste_segment'),
                shortcut: 'Ctrl+V',
                disabled: !copiedSegmentId.value,
                run: () => {
                    if (
                        copiedSegmentId.value &&
                        !editor.copySegmentToShelf(copiedSegmentId.value, target.id)
                    ) {
                        toast.error(t('plannerate.editor.clipboard.paste_failed'));
                    }
                },
            },
            { separator: true },
            {
                label: t('plannerate.editor.context_menu.move_up'),
                shortcut: 'Ctrl+↑',
                disabled: !actions.canMoveUp.value,
                run: () => actions.moveUp(),
            },
            {
                label: t('plannerate.editor.context_menu.move_down'),
                shortcut: 'Ctrl+↓',
                disabled: !actions.canMoveDown.value,
                run: () => actions.moveDown(),
            },
            {
                label: t('plannerate.editor.context_menu.move_prev_section'),
                shortcut: 'Ctrl+←',
                disabled: !actions.canMoveLeft.value,
                run: () => actions.moveLeft(),
            },
            {
                label: t('plannerate.editor.context_menu.move_next_section'),
                shortcut: 'Ctrl+→',
                disabled: !actions.canMoveRight.value,
                run: () => actions.moveRight(),
            },
            { separator: true },
            {
                label: t('plannerate.editor.context_menu.invert_segments'),
                shortcut: 'Ctrl+I',
                run: () => editor.invertSegmentsOrder(target.id),
            },
            { separator: true },
            {
                label: t('plannerate.editor.context_menu.delete'),
                shortcut: 'Del',
                danger: true,
                run: () => requestDelete('shelf', found.shelf as Shelf),
            },
        ];
    }

    // target.type === 'section'
    const section = findSectionById(target.id);

    if (!section) {
        return [];
    }

    const actions = useSectionActions(() => section as Section);

    return [
        {
            label: t('plannerate.editor.context_menu.move_left'),
            shortcut: 'Ctrl+←',
            disabled: !actions.canMoveLeft.value,
            run: () => actions.moveLeft(),
        },
        {
            label: t('plannerate.editor.context_menu.move_right'),
            shortcut: 'Ctrl+→',
            disabled: !actions.canMoveRight.value,
            run: () => actions.moveRight(),
        },
        { separator: true },
        {
            label: t('plannerate.editor.context_menu.invert_shelves'),
            shortcut: 'Ctrl+I',
            disabled: !actions.canInvertShelves.value,
            run: () => actions.invertShelves(),
        },
        {
            label: t('plannerate.editor.context_menu.duplicate'),
            shortcut: 'Ctrl+D',
            run: () => {
                sectionToDuplicate.value = section as Section;
                showDuplicateSectionDialog.value = true;
            },
        },
        { separator: true },
        {
            label: t('plannerate.editor.context_menu.delete'),
            shortcut: 'Del',
            danger: true,
            run: () => requestDelete('section', section as Section),
        },
    ];
});
</script>
