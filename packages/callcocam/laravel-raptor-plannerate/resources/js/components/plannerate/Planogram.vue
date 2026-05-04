<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { PanelLeftOpen, PanelRightOpen } from 'lucide-vue-next';
import { computed, onMounted, provide, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { updateImages } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/GondolaController';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramKeyboard } from '@/composables/plannerate/usePlanogramKeyboard';
import { wayfinderPath } from '../../libs/wayfinderPath';
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

interface ProductImagesUpdatedPayload {
    gondola_id: string;
    processed_count: number;
}

interface AuthPageProps {
    [key: string]: unknown;
    subdomain?: string;
    auth?: {
        user?: {
            id?: string;
        };
    };
}

const props = defineProps<Props>();
const page = usePage<AuthPageProps>();
const isBrowser = typeof window !== 'undefined';

const resolvedSubdomain = computed(() => {
    const subdomainFromPage = page.props.subdomain?.toString().trim();

    if (subdomainFromPage) {
        return subdomainFromPage;
    }

    if (!isBrowser) {
        return '';
    }

    return window.location.hostname.split('.')[0] || '';
});
// Usa o composable para gerenciar o estado
const editor = usePlanogramEditor();
const authUserId = computed(() => page.props.auth?.user?.id);

// Inicializa o editor imediatamente se houver record
if (props.record) {
    editor.initializeEditor(props.record);
    editor.setSaveChangesRoute(props.saveChangesRoute || '');
}

// Inicializa handlers de teclado centralizados
const keyboard = usePlanogramKeyboard();

const headerAndToolbar = ref<HTMLElement | null>(null);

const containerHeight = ref<number>(0);
const storedProductsPanel = ref(false);
const storedPropertiesPanel = ref(false);

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

const reloadEditorRecord = () => {
    router.reload({
        only: ['record'],
    });
};

const saveChangesAndReloadEditorRecord = async () => {
    const loadingToastId = toast.loading('Salvando alterações antes de recarregar...');

    try {
        const saved = editor.hasChanges.value ? await editor.save() : true;

        toast.dismiss(loadingToastId);

        if (!saved) {
            toast.error('Não foi possível salvar as alterações.');

            return;
        }

        reloadEditorRecord();
        toast.success('Alterações salvas. Recarregando imagens...');
    } catch {
        toast.dismiss(loadingToastId);
        toast.error('Erro ao salvar antes de recarregar as imagens.');
    }
};

const handleProductImagesUpdated = (payload: ProductImagesUpdatedPayload) => {
    if (payload.gondola_id !== props.record?.id) {
return;
}

    if (!editor.hasChanges.value) {
        toast.success(
            `Imagens atualizadas: ${payload.processed_count} produto(s) processado(s).`,
        );
        reloadEditorRecord();

        return;
    }

    toast.warning('Imagens atualizadas, mas há alterações não salvas.', {
        description: 'Salve o planograma antes de recarregar para não perder mudanças.',
        duration: 15000,
        action: {
            label: 'Salvar e recarregar',
            onClick: () => {
                void saveChangesAndReloadEditorRecord();
            },
        },
    });
};

const handleUpdateGondolaImages = () => {
    const gondolaId = props.record?.id;
    const subdomain = resolvedSubdomain.value;

    if (!gondolaId || !subdomain) {
        return;
    }

    router.post(
        wayfinderPath(updateImages.url({ subdomain, gondola: gondolaId })),
        {},
        {
            preserveScroll: true,
        },
    );
};

watch(
    () => props.record,
    (record) => {
        if (!record) {
return;
}

        editor.initializeEditor(record);
        editor.setSaveChangesRoute(props.saveChangesRoute || '');
    },
);

if (isBrowser && authUserId.value) {
    useEcho<ProductImagesUpdatedPayload>(
        `App.Models.User.${authUserId.value}`,
        '.plannerate.gondola.product-images.updated',
        handleProductImagesUpdated,
    );
}

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
    // Restaura painéis do localStorage somente após hidratação
    if (isBrowser) {
        storedProductsPanel.value =
            window.localStorage.getItem('planogram-products-manual-open') ===
            'true';
        storedPropertiesPanel.value =
            window.localStorage.getItem('planogram-properties-manual-open') ===
            'true';
    }

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
                :subdomain="resolvedSubdomain"
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
