/**
 * Estado do menu de contexto do editor (botão direito no canvas).
 *
 * UM menu global (renderizado em PlanogramEditor.vue) alimentado por estas
 * refs module-level — evita instanciar um wrapper de menu por
 * segmento/prateleira/seção (componentes quentes do canvas).
 */
import { ref } from 'vue';

export type ContextMenuTargetType = 'segment' | 'shelf' | 'section';

export interface ContextMenuTarget {
    type: ContextMenuTargetType;
    id: string;
}

const isContextMenuOpen = ref(false);
const contextMenuX = ref(0);
const contextMenuY = ref(0);
const contextMenuTarget = ref<ContextMenuTarget | null>(null);

export function useEditorContextMenu() {
    /**
     * Abre o menu no ponto do clique para o alvo dado. O caller é responsável
     * por selecionar o item antes (o menu opera sobre o alvo, e a seleção
     * mantém o painel de propriedades coerente com o que o menu mostra).
     */
    function openContextMenu(
        event: MouseEvent,
        type: ContextMenuTargetType,
        id: string,
    ): void {
        event.preventDefault();
        event.stopPropagation();

        contextMenuX.value = event.clientX;
        contextMenuY.value = event.clientY;
        contextMenuTarget.value = { type, id };
        isContextMenuOpen.value = true;
    }

    function closeContextMenu(): void {
        isContextMenuOpen.value = false;
        contextMenuTarget.value = null;
    }

    return {
        isContextMenuOpen,
        contextMenuX,
        contextMenuY,
        contextMenuTarget,
        openContextMenu,
        closeContextMenu,
    };
}
