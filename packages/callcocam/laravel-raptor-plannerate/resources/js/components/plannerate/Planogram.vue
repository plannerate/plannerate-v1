<script setup lang="ts">
import { updateImages } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/GondolaController';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramKeyboard } from '@/composables/plannerate/usePlanogramKeyboard';
import { router } from '@inertiajs/vue3';
import { PanelLeftOpen, PanelRightOpen } from 'lucide-vue-next';
import { computed, onMounted, provide, ref } from 'vue';
import Canvas from './Canvas.vue';
import ConfirmDeleteDialog from './editor/ConfirmDeleteDialog.vue';
import DuplicateSectionDialog from './editor/DuplicateSectionDialog.vue';
import Header from './header/Header.vue';
import Toolbar from './header/Toolbar.vue';
import PanelLeft from './sidebar/products/PanelLeft.vue';
import PanelRight from './sidebar/properties/PanelRight.vue';
defineOptions({
    inheritAttrs: false,
});

interface Props {
    record?: any;
    availableUsers?: Array<{ id: string; name: string }>;
    saveChangesRoute?: string;
    backRoute?: string;
    analysis?: {
        abc?: any;
        stock?: any;
    };
    permissions: {
        can_create_gondola: boolean;
        can_update_gondola: boolean;
        can_remove_gondola: boolean;
        can_autogenate_gondola: boolean;
        can_autogenate_gondola_ia: boolean;
    };
}

const props = defineProps<Props>();
const isBrowser = typeof window !== 'undefined';
// Usa o composable para gerenciar o estado
const editor = usePlanogramEditor();

// Inicializa o editor imediatamente se houver record
if (props.record) {
    editor.initializeEditor(props.record);
    editor.setSaveChangesRoute(props.saveChangesRoute || '');
}
// Inicializa handlers de teclado centralizados
const keyboard = usePlanogramKeyboard();

const headerAndToolbar = ref<HTMLElement | null>(null);

const containerHeight = ref<number>(0);
// Restaura painéis (produtos e propriedades) do localStorage
const storedProductsPanel = ref(
    isBrowser &&
        window.localStorage.getItem('planogram-products-manual-open') ===
            'true',
);
const storedPropertiesPanel = ref(
    isBrowser &&
        window.localStorage.getItem('planogram-properties-manual-open') ===
            'true',
);

// Ref para armazenar a função de reload vinda do PanelLeft
const reloadProductsListFn = ref<(() => Promise<void>) | null>(null);

// Ref para armazenar a função removeUsedProduct vinda do PanelLeft
const removeUsedProductFn = ref<((productId: string) => void) | null>(null);

// Fornece a função para todos os componentes filhos (incluindo PanelRight)
provide('reloadProductsList', () => {
    if (reloadProductsListFn.value) {
        return reloadProductsListFn.value();
    }
    return Promise.resolve();
});

// Fornece removeUsedProduct para todos os componentes filhos (incluindo Shelf.vue)
provide('removeUsedProduct', (productId: string) => {
    if (removeUsedProductFn.value) {
        removeUsedProductFn.value(productId);
    }
});

// Função para receber a função de reload do PanelLeft
const setReloadFunction = (fn: () => Promise<void>) => {
    reloadProductsListFn.value = fn;
};

// Função para receber a função removeUsedProduct do PanelLeft
const setRemoveUsedProductFunction = (fn: (productId: string) => void) => {
    removeUsedProductFn.value = fn;
};

const goBack = () => {
    router.get(props.backRoute || '/plannerates');
};

const handleUpdateGondolaImages = () => {
    const gondolaId = props.record?.id;
    if (!gondolaId) return;

    router.post(
        updateImages.url(gondolaId),
        {},
        {
            preserveScroll: true,
        },
    );
};

// Funções separadas para abrir/fechar (não toggle)
const openProperties = () => {
    storedPropertiesPanel.value = true;
    if (isBrowser) {
        window.localStorage.setItem('planogram-properties-manual-open', 'true');
    }
};

const closeProperties = () => {
    storedPropertiesPanel.value = false;
    if (isBrowser) {
        window.localStorage.setItem(
            'planogram-properties-manual-open',
            'false',
        );
    }
};

const toggleProperties = () => {
    storedPropertiesPanel.value = !storedPropertiesPanel.value;
    if (isBrowser) {
        window.localStorage.setItem(
            'planogram-properties-manual-open',
            storedPropertiesPanel.value ? 'true' : 'false',
        );
    }
};

const openProducts = () => {
    storedProductsPanel.value = true;
    if (isBrowser) {
        window.localStorage.setItem('planogram-products-manual-open', 'true');
    }
};

const closeProducts = () => {
    storedProductsPanel.value = false;
    if (isBrowser) {
        window.localStorage.setItem('planogram-products-manual-open', 'false');
    }
};

const toggleProducts = () => {
    storedProductsPanel.value = !storedProductsPanel.value;
    if (isBrowser) {
        window.localStorage.setItem(
            'planogram-products-manual-open',
            storedProductsPanel.value ? 'true' : 'false',
        );
    }
};

onMounted(() => {
    if (props.record) {
        editor.initializeEditor(props.record);
        editor.setSaveChangesRoute(props.saveChangesRoute || '');
    }
    if (headerAndToolbar.value) {
        containerHeight.value =
            window.innerHeight - (headerAndToolbar.value?.offsetHeight || 0);
    }

    // Atualiza altura quando redimensionar
    const resizeObserver = new ResizeObserver(() => {
        if (headerAndToolbar.value) {
            containerHeight.value =
                window.innerHeight -
                (headerAndToolbar.value?.offsetHeight || 0);
        }
    });

    if (headerAndToolbar.value) {
        resizeObserver.observe(headerAndToolbar.value);
    }
});
const category = computed(() => {
    return props.record?.planogram?.category;
});
</script>

<template>
    <div class="h-screen overflow-hidden bg-background">
        <div ref="headerAndToolbar" class="flex flex-col">
            <Header
                :title="record.planogram?.name || 'Editor v3'"
                :status="record.planogram?.status || 'draft'"
                :tenant="record.tenant"
                :planogram-id="record.planogram_id"
                :available-users="availableUsers || []"
                :back-route="props.backRoute"
                @go-back="goBack"
                @update-gondola-images="handleUpdateGondolaImages"
                :permissions="permissions"
            />
            <Toolbar :analysis="analysis" :permissions="permissions"/>
        </div>
        <div
            class="relative flex overflow-hidden"
            :style="{ height: `${containerHeight}px` }"
        >
            <PanelLeft
                :open="storedProductsPanel"
                :gondola-id="record.id"
                :planogram-id="record.planogram_id"
                :category="category"
                @close="closeProducts"
                @reload-function="setReloadFunction"
                @remove-used-product="setRemoveUsedProductFunction"
            />
            <Canvas
                :record="record"
                :containerHeight="containerHeight"
                :openProperties="storedPropertiesPanel"
                :openProducts="storedProductsPanel"
                :saveChangesRoute="saveChangesRoute"
                @openProperties="openProperties"
                @closeProperties="closeProperties"
                @openProducts="openProducts"
                @closeProducts="closeProducts"
            />
            <PanelRight
                :open="storedPropertiesPanel"
                @close="closeProperties"
            />

            <button
                class="group absolute top-10 left-0 z-10 flex items-center rounded-r border-t border-r border-b border-border bg-background p-1 shadow-sm transition-colors hover:bg-accent"
                type="button"
                @click="toggleProducts"
                v-if="!storedProductsPanel"
            >
                <PanelLeftOpen
                    class="size-4 shrink-0 text-foreground transition-all duration-300 group-hover:mr-2"
                />
                <span
                    class="max-w-0 overflow-hidden whitespace-nowrap text-foreground transition-all duration-300 group-hover:ml-1 group-hover:max-w-xs"
                >
                    Abrir produtos
                </span>
            </button>

            <button
                class="group absolute top-10 right-0 z-10 flex items-center rounded-l border-t border-b border-l border-border bg-background p-1 shadow-sm transition-colors hover:bg-accent"
                type="button"
                @click="toggleProperties"
                v-if="!storedPropertiesPanel"
            >
                <span
                    class="max-w-0 overflow-hidden whitespace-nowrap text-foreground transition-all duration-300 group-hover:mr-1 group-hover:max-w-xs"
                >
                    Abrir propriedades
                </span>
                <PanelRightOpen
                    class="size-4 shrink-0 text-foreground transition-all duration-300 group-hover:ml-2"
                />
            </button>
        </div>

        <!-- Modal de duplicação de seção -->
        <DuplicateSectionDialog
            :open="keyboard.showDuplicateSectionDialog.value"
            :section="keyboard.sectionToDuplicate.value || undefined"
            @update:open="
                (val) => (keyboard.showDuplicateSectionDialog.value = val)
            "
            @confirm="keyboard.handleDuplicateSectionConfirm"
        />

        <!-- Modal de confirmação de exclusão -->
        <ConfirmDeleteDialog
            :open="keyboard.showDeleteConfirmDialog.value"
            :type="keyboard.itemToDelete.value?.type"
            :item="keyboard.itemToDelete.value?.item"
            @update:open="
                (val) => (keyboard.showDeleteConfirmDialog.value = val)
            "
            @confirm="keyboard.handleDeleteConfirm"
        />
    </div>
</template>
