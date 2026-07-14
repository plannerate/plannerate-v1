<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ArrowLeft, MapPin, Plus, RefreshCcw, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NotificationsDropdown from '@/components/NotificationsDropdown.vue';
import GondolaCreateStepper from '@/components/plannerate/form/GondolaCreateStepper.vue';
import GondolaEditForm from '@/components/plannerate/form/GondolaEditForm.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { useT } from '@/composables/useT';
import { wayfinderPath } from '../../../libs/wayfinderPath';
import ConfirmDeleteGondolaDialog from './ConfirmDeleteGondolaDialog.vue';
import MapRegionSelectorModal from './MapRegionSelectorModal.vue';

interface Props {
    title?: string;
    status?: string;
    planogramId?: string;
    tenant?: any;
    availableUsers?: Array<{ id: string; name: string }>;
    analysis?: {
        abc?: any;
        stock?: any;
    };
    permissions: {
        can_create_gondola: boolean;
        can_update_gondola: boolean;
        can_remove_gondola?: boolean;
    };
    backRoute?: string;
    sidebar?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
    title: '',
    status: 'draft',
    planogramId: '',
    tenant: {},
    availableUsers: () => [],
    backRoute: '',
    sidebar: false,
});
const { t } = useT();
const emit = defineEmits<{
    closeProducts: [];
    closeProperties: [];
    goBack: [];
    importData: [];
    addGondola: [];
    updateGondolaImages: [];
}>();

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        draft: 'bg-yellow-500/10 text-yellow-500',
        published: 'bg-green-500/10 text-green-500',
        archived: 'bg-gray-500/10 text-gray-500',
    };

    return colors[status] || colors.draft;
};

const gondolaSettings = computed(() => {
    return props.tenant?.settings?.gondola || {};
});
const titleDisplay = computed(() => props.title || t('plannerate.header.planogram'));

// Sheet de edição da gôndola
const showGondolaEdit = ref(false);

function closeGondolaEdit() {
    showGondolaEdit.value = false;
}

// Sheet de criação de gôndola
const showGondolaCreate = ref(false);

function openGondolaCreate() {
    showGondolaCreate.value = true;
}

function closeGondolaCreate() {
    showGondolaCreate.value = false;
}

function handleGondolaCreated() {
    closeGondolaCreate();
    // Emit evento para recarregar dados se necessário
    emit('addGondola');
}

const showUpdateImagesConfirm = ref(false);

function handleUpdateGondolaImages() {
    showUpdateImagesConfirm.value = true;
}

function confirmUpdateGondolaImages() {
    emit('updateGondolaImages');
    showUpdateImagesConfirm.value = false;
}

function cancelUpdateGondolaImages() {
    showUpdateImagesConfirm.value = false;
}

// ============================================================
// AÇÕES MOVIDAS DO DropdownActions (Mapa + Remover Gôndola)
// ============================================================

/**
 * Acessa o estado global do editor (singleton) para a gôndola atual,
 * remoção de gôndola e vínculo com região do mapa.
 */
const editor = usePlanogramEditor();

/**
 * Gôndola atualmente ativa no editor.
 */
const currentGondola = computed(() => editor.currentGondola.value);

/**
 * Verifica se o planograma tem loja associada (store_id).
 */
const hasStore = computed(() => {
    const planogram = currentGondola.value?.planogram as any;

    return !!planogram?.store_id;
});

/**
 * Dados da loja para o mapa.
 */
const storeData = computed(() => {
    const planogram = currentGondola.value?.planogram as any;

    return planogram?.store || null;
});

/**
 * URL pública da imagem do mapa da loja.
 */
const mapImageUrl = computed(() => {
    const store = storeData.value;

    if (!store?.map_image_path) {
        return null;
    }

    return `/storage/${store.map_image_path}`;
});

/**
 * Regiões do mapa da loja.
 */
const mapRegions = computed(() => {
    const store = storeData.value;

    return store?.map_regions || [];
});

/**
 * ID da região vinculada à gôndola atual.
 */
const currentMapRegionId = computed(() => {
    return currentGondola.value?.linked_map_gondola_id || null;
});

/**
 * Estado do modal de seleção de região do mapa.
 */
const showMapRegionSelector = ref(false);

/**
 * Estado do diálogo de confirmação de remoção da gôndola.
 *
 * IMPORTANTE: `showDeleteConfirmation` é criado por instância dentro de
 * usePlanogramEditor() (não é module-level como `currentGondola`). Por isso o
 * gatilho (`editor.removeGondola()`) e o diálogo precisam viver no MESMO
 * componente — ambos usam esta mesma instância de `editor`.
 */
const showDeleteConfirmation = computed({
    get: () => editor.showDeleteConfirmation.value,
    set: (val) => (editor.showDeleteConfirmation.value = val),
});

/**
 * Nome da gôndola atual para exibir no diálogo de confirmação.
 */
const currentGondolaName = computed(() => currentGondola.value?.name || '');

/**
 * Atualiza a gôndola com a região selecionada (e seu tipo/categoria).
 */
function handleMapRegionSelect(regionId: string | null) {
    if (!currentGondola.value) {
        return;
    }

    const region = mapRegions.value.find((r: any) => r.id === regionId);

    editor.updateGondola({
        linked_map_gondola_id: regionId,
        linked_map_gondola_category: region?.type || null,
    });
}
</script>

<template>
    <div class="border-b bg-background">
        <div :class="sidebar ? 'flex flex-col gap-3 p-4' : 'flex h-16 items-center justify-between px-6'">
            <!-- Title & Status -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Marca Plannerate (somente no top bar completo) -->
                <template v-if="!sidebar">
                    <!-- Versão escura do texto para fundo claro; troca no modo escuro -->
                    <img src="/img/marca-claro.png" alt="Plannerate"
                        class="h-7 w-auto shrink-0 object-contain dark:hidden" />
                    <img src="/img/marcadark.png" alt="Plannerate"
                        class="hidden h-7 w-auto shrink-0 object-contain dark:block" />
                    <div class="mx-1 h-6 w-px shrink-0 bg-border" aria-hidden="true"></div>
                </template>
                <h1 :title="titleDisplay"
                    :class="sidebar ? 'text-base font-semibold truncate' : 'text-xl font-semibold truncate max-w-[160px] sm:max-w-[240px] xl:max-w-[360px]'">
                    {{ titleDisplay }}
                </h1>
                <Badge :class="getStatusColor(status)" variant="outline">
                    {{ status }}
                </Badge>
            </div>

            <!-- Actions -->
            <div :class="sidebar ? 'flex flex-wrap gap-2' : 'flex items-center gap-2'">
                <Button variant="outline" size="sm" class="gap-2" disabled @click="emit('importData')">
                    <Upload />
                    {{ t('plannerate.header.import_data') }}
                </Button>

                <Button variant="outline" size="sm" class="gap-2" @click="openGondolaCreate"
                    v-if="permissions.can_create_gondola">
                    <Plus />
                    {{ t('plannerate.header.add_gondola') }}
                </Button>

                <!-- <Button variant="outline" size="sm" class="gap-2" @click="openGondolaEdit"
                    v-if="permissions.can_update_gondola">
                    <Edit />
                    {{ t('plannerate.header.edit_gondola') }}
                </Button> -->


                <!-- Remover gôndola atual -->
                <Button v-if="permissions.can_remove_gondola" variant="outline" size="sm"
                    class="gap-2 text-destructive hover:text-destructive" @click="editor.removeGondola()">
                    <Trash2 />
                    {{ t('plannerate.toolbar.remove_gondola') }}
                </Button>

                <Button variant="outline" size="sm" class="gap-2" @click="handleUpdateGondolaImages">
                    <RefreshCcw />
                    {{ t('plannerate.header.update_images') }}
                </Button>

                <!-- Modal de confirmação: Atualizar Imagens -->
                <AlertDialog :open="showUpdateImagesConfirm" @update:open="(val) => (showUpdateImagesConfirm = val)">
                    <AlertDialogContent class="z-[1000] sm:max-w-md">
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('plannerate.header.confirm_update_images_title') }}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ t('plannerate.header.confirm_update_images_description') }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel @click="cancelUpdateGondolaImages">
                                {{ t('app.actions.cancel') }}
                            </AlertDialogCancel>
                            <AlertDialogAction @click="confirmUpdateGondolaImages">
                                {{ t('plannerate.header.confirm_update_images_action') }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <!-- Vincular gôndola a uma região do mapa da loja -->
                <Button v-if="hasStore" variant="outline" size="sm" class="gap-2" @click="showMapRegionSelector = true">
                    <MapPin />
                    {{ currentMapRegionId ? t('plannerate.toolbar.map_remove') : t('plannerate.toolbar.map_store') }}
                </Button>
                <Link v-if="backRoute" :href="wayfinderPath(backRoute)"
                    class="flex items-center gap-2 text-sm text-muted-foreground hover:text-muted-foreground/80 cursor-pointer">
                    <ArrowLeft class="size-4" />
                    {{ t('app.actions.back') }}
                </Link>

                <!-- Sino de notificações (mesmo componente da topbar principal) -->
                <NotificationsDropdown />
            </div>
        </div>
    </div>

    <!-- Sheet de Edição da Gôndola -->
    <Sheet v-model:open="showGondolaEdit">
        <SheetContent side="right" class="w-full p-0 sm:max-w-md">
            <SheetTitle class="sr-only">{{ t('plannerate.header.edit_gondola') }}</SheetTitle>
            <SheetDescription class="sr-only">
                Formulário para editar propriedades da gôndola
            </SheetDescription>
            <GondolaEditForm @close="closeGondolaEdit" />
        </SheetContent>
    </Sheet>

    <!-- Sheet de Criação de Gôndola -->
    <GondolaCreateStepper :open="showGondolaCreate" :planogram-id="planogramId" :available-users="availableUsers"
        :gondola-settings="gondolaSettings" @update:open="(val) => (showGondolaCreate = val)"
        @success="handleGondolaCreated" />

    <!-- Modal de Seleção de Região do Mapa -->
    <MapRegionSelectorModal v-model:open="showMapRegionSelector" :store-id="storeData?.id" :store-name="storeData?.name"
        :map-image-url="mapImageUrl" :map-regions="mapRegions" :current-region-id="currentMapRegionId"
        :gondola-id="currentGondola?.id" :gondola-name="currentGondola?.name" @select="handleMapRegionSelect" />

    <!-- Diálogo de Confirmação de Remoção de Gôndola -->
    <ConfirmDeleteGondolaDialog v-model:open="showDeleteConfirmation" :gondola-name="currentGondolaName"
        @confirm="editor.confirmRemoveGondola()" />
</template>
