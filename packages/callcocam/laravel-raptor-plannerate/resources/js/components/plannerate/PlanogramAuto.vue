<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { PanelLeftOpen, PanelRightOpen } from 'lucide-vue-next';
import { computed, onMounted, provide, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { updateImages } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/GondolaController';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramKeyboard } from '@/composables/plannerate/interactions/usePlanogramKeyboard';
import { useT } from '@/composables/useT';
import { wayfinderPath } from '../../libs/wayfinderPath';
import Canvas from './Canvas.vue';
import ConfirmDeleteDialog from './editor/ConfirmDeleteDialog.vue';
import DuplicateSectionDialog from './editor/DuplicateSectionDialog.vue';
import Header from './header/Header.vue';
import Toolbar from './header/Toolbar.vue';
import ToolbarDrawer from './header/ToolbarDrawer.vue';
import PanelLeftGeneration from './sidebar/PanelLeftGeneration.vue';
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
const isEchoConfigured = isBrowser && window.__plannerateEchoConfigured === true;

const { t } = useT();

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

const editor = usePlanogramEditor();
const authUserId = computed(() => page.props.auth?.user?.id);

if (props.record) {
    editor.initializeEditor(props.record);
    editor.setSaveChangesRoute(props.saveChangesRoute || '');
}

const keyboard = usePlanogramKeyboard();

const headerAndToolbar = ref<HTMLElement | null>(null);
const containerHeight = ref<number>(0);
const storedGenerationPanel = ref(false);
const storedPropertiesPanel = ref(false);

// No-ops providos para compatibilidade com PanelRight e Shelf.vue
// que fazem inject desses symbols mas não são usados neste modo
provide('reloadProductsList', () => Promise.resolve());
provide('removeUsedProduct', (_: string) => {});

const reloadEditorRecord = () => {
    router.reload({ only: ['record'] });
};

const saveChangesAndReloadEditorRecord = async () => {
    const loadingToastId = toast.loading(t('plannerate.planogram.toast.saving_before_reload'));

    try {
        const saved = editor.hasChanges.value ? await editor.save() : true;

        toast.dismiss(loadingToastId);

        if (!saved) {
            toast.error(t('plannerate.planogram.toast.save_failed'));
            return;
        }

        reloadEditorRecord();
        toast.success(t('plannerate.planogram.toast.saved_reloading'));
    } catch {
        toast.dismiss(loadingToastId);
        toast.error(t('plannerate.planogram.toast.save_before_reload_error'));
    }
};

const handleProductImagesUpdated = (payload: ProductImagesUpdatedPayload) => {
    if (payload.gondola_id !== props.record?.id) {
        return;
    }

    if (!editor.hasChanges.value) {
        toast.success(t('plannerate.planogram.toast.images_updated_count', { count: String(payload.processed_count) }));
        reloadEditorRecord();
        return;
    }

    toast.warning(t('plannerate.planogram.toast.images_updated_unsaved_title'), {
        description: t('plannerate.planogram.toast.images_updated_unsaved_description'),
        duration: 15000,
        action: {
            label: t('plannerate.planogram.toast.save_and_reload'),
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
        wayfinderPath(updateImages.url({ gondola: gondolaId })),
        {},
        { preserveScroll: true },
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

if (isEchoConfigured && authUserId.value) {
    useEcho<ProductImagesUpdatedPayload>(
        `App.Models.User.${authUserId.value}`,
        '.plannerate.gondola.product-images.updated',
        handleProductImagesUpdated,
    );
}

const openProperties = () => {
    storedPropertiesPanel.value = true;
    if (isBrowser) {
        window.localStorage.setItem('planogram-properties-manual-open', 'true');
    }
};

const closeProperties = () => {
    storedPropertiesPanel.value = false;
    if (isBrowser) {
        window.localStorage.setItem('planogram-properties-manual-open', 'false');
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

const openGeneration = () => {
    storedGenerationPanel.value = true;
    if (isBrowser) {
        window.localStorage.setItem('planogram-generation-panel-open', 'true');
    }
};

const closeGeneration = () => {
    storedGenerationPanel.value = false;
    if (isBrowser) {
        window.localStorage.setItem('planogram-generation-panel-open', 'false');
    }
};

const toggleGeneration = () => {
    storedGenerationPanel.value = !storedGenerationPanel.value;
    if (isBrowser) {
        window.localStorage.setItem(
            'planogram-generation-panel-open',
            storedGenerationPanel.value ? 'true' : 'false',
        );
    }
};

onMounted(() => {
    if (isBrowser) {
        const stored = window.localStorage.getItem('planogram-generation-panel-open');
        // Abre por padrão na primeira visita
        storedGenerationPanel.value = stored === null ? true : stored === 'true';
        storedPropertiesPanel.value =
            window.localStorage.getItem('planogram-properties-manual-open') === 'true';
    }

    if (props.record) {
        editor.initializeEditor(props.record);
        editor.setSaveChangesRoute(props.saveChangesRoute || '');
    }

    if (headerAndToolbar.value) {
        containerHeight.value = window.innerHeight - (headerAndToolbar.value?.offsetHeight || 0);
    }

    const resizeObserver = new ResizeObserver(() => {
        if (headerAndToolbar.value) {
            containerHeight.value = window.innerHeight - (headerAndToolbar.value?.offsetHeight || 0);
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
        <!-- Top bar: visível apenas em telas xl+ -->
        <div ref="headerAndToolbar" class="hidden xl:flex xl:flex-col">
            <Header
                :title="record.planogram?.name || t('plannerate.planogram.editor_fallback_title')"
                :status="record.planogram?.status || 'draft'"
                :tenant="record.tenant"
                :planogram-id="record.planogram_id"
                :available-users="availableUsers || []"
                :back-route="props.backRoute"
                @update-gondola-images="handleUpdateGondolaImages"
                :permissions="permissions"
            />
            <Toolbar :analysis="analysis" :permissions="permissions" />
        </div>

        <div class="relative flex overflow-hidden" :style="{ height: `${containerHeight}px` }">
            <!-- Drawer lateral: visível apenas em telas menores que xl -->
            <ToolbarDrawer
                class="xl:hidden"
                :title="record.planogram?.name || t('plannerate.planogram.editor_fallback_title')"
                :status="record.planogram?.status || 'draft'"
                :tenant="record.tenant"
                :planogram-id="record.planogram_id"
                :available-users="availableUsers || []"
                :back-route="props.backRoute"
                :permissions="permissions"
                @update-gondola-images="handleUpdateGondolaImages"
            />
            <PanelLeftGeneration
                :open="storedGenerationPanel"
                :gondola="record"
                @close="closeGeneration"
            />

            <Canvas
                :record="record"
                :containerHeight="containerHeight"
                :openProperties="storedPropertiesPanel"
                :openProducts="storedGenerationPanel"
                :saveChangesRoute="saveChangesRoute"
                @openProperties="openProperties"
                @closeProperties="closeProperties"
                @openProducts="openGeneration"
                @closeProducts="closeGeneration"
            />

            <PanelRight :open="storedPropertiesPanel" @close="closeProperties" />

            <button
                class="group absolute top-10 left-0 z-10 flex items-center rounded-r border-t border-r border-b border-border bg-background p-1 shadow-sm transition-colors hover:bg-accent"
                type="button"
                @click="toggleGeneration"
                v-if="!storedGenerationPanel"
            >
                <PanelLeftOpen class="size-4 shrink-0 text-foreground transition-all duration-300 group-hover:mr-2" />
                <span class="max-w-0 overflow-hidden whitespace-nowrap text-foreground transition-all duration-300 group-hover:ml-1 group-hover:max-w-xs">
                    {{ t('plannerate.sidebar.generation.title') }}
                </span>
            </button>

            <button
                class="group absolute top-10 right-0 z-10 flex items-center rounded-l border-t border-b border-l border-border bg-background p-1 shadow-sm transition-colors hover:bg-accent"
                type="button"
                @click="toggleProperties"
                v-if="!storedPropertiesPanel"
            >
                <span class="max-w-0 overflow-hidden whitespace-nowrap text-foreground transition-all duration-300 group-hover:mr-1 group-hover:max-w-xs">
                    {{ t('plannerate.planogram.open_properties') }}
                </span>
                <PanelRightOpen class="size-4 shrink-0 text-foreground transition-all duration-300 group-hover:ml-2" />
            </button>
        </div>

        <DuplicateSectionDialog
            :open="keyboard.showDuplicateSectionDialog.value"
            :section="keyboard.sectionToDuplicate.value || undefined"
            @update:open="(val) => (keyboard.showDuplicateSectionDialog.value = val)"
            @confirm="keyboard.handleDuplicateSectionConfirm"
        />

        <ConfirmDeleteDialog
            :open="keyboard.showDeleteConfirmDialog.value"
            :type="keyboard.itemToDelete.value?.type"
            :item="keyboard.itemToDelete.value?.item"
            @update:open="(val) => (keyboard.showDeleteConfirmDialog.value = val)"
            @confirm="keyboard.handleDeleteConfirm"
        />
    </div>
</template>
