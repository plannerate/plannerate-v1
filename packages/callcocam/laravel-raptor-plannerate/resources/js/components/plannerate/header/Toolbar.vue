<script setup lang="ts">
// ============================================================================
// IMPORTS
// ============================================================================

// UI Components
import AddModuleSheet from '@/components/plannerate/form/AddModuleSheet.vue';
import TransferSectionDialog from '@/components/plannerate/sidebar/properties/partials/TransferSectionDialog.vue';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import AutoGenerateModal from './AutoGenerateModal.vue';
import ConfirmDeleteGondolaDialog from './ConfirmDeleteGondolaDialog.vue';
import MapRegionSelectorModal from './MapRegionSelectorModal.vue';

// Composables (Estado Global)
import {
    currentGondola,
    eanSearchQuery,
    showPerformanceModal,
} from '@/composables/plannerate/v3/editor/useGondolaState';
import { usePlanogramChanges } from '@/composables/plannerate/v3/usePlanogramChanges';
import { usePlanogramEditor } from '@/composables/plannerate/v3/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/v3/usePlanogramSelection';

// Types

// Inertia
import { Link, usePage } from '@inertiajs/vue3';

// Icons
import {
    AlignCenter,
    AlignHorizontalDistributeCenter,
    AlignLeft,
    AlignRight,
    ArrowRightLeft,
    FlipHorizontal,
    Grid3x3,
    MapPin,
    Minus,
    Plus,
    Redo2,
    Save,
    Search,
    Sparkles,
    Trash2,
    Undo2,
    X,
} from 'lucide-vue-next';

// Vue
import { Gondola } from '@/types/planogram';
import { computed, ref, watch } from 'vue';
import DropdownActions from '../DropdownActions.vue';
import DropdownPerformance from '../DropdownPerformance.vue';
import Performance from './Performance.vue';

// ============================================================================
// COMPOSABLES (ESTADO GLOBAL)
// ============================================================================

/**
 * Acessa o estado global do editor de planogramas
 * Singleton - mesma instância compartilhada entre todos os componentes
 */
const editor = usePlanogramEditor();

/**
 * Acessa o sistema de tracking de mudanças (delta/diff)
 * Usado para undo/redo e auto-save
 */
const changes = usePlanogramChanges();

/**
 * Acessa o sistema de seleção de itens
 * Usado para verificar seção selecionada
 */
const selection = usePlanogramSelection();

/**
 * Acessa props compartilhadas via Inertia (feature flags, etc)
 */
const page = usePage();

// ============================================================================
// PROPS & EMITS
// ============================================================================

// Props comentadas - estado vem dos composables agora
// Componente sem props - todo estado gerenciado por composables

// Emits removidos - funções chamadas diretamente do composable agora

// ============================================================================
// COMPUTED PROPERTIES (ESCUTANDO COMPOSABLES)
// ============================================================================

/**
 * Lista de todas as gôndolas disponíveis no planograma
 * Usado para navegação entre gôndolas
 */
const gondolas = computed(() => editor.gondolasAvailable());

/**
 * ID da gôndola atualmente ativa
 * Usado para highlight na navegação
 */
const currentGondolaId = computed(() => editor.currentGondola.value?.id || '');

/**
 * Fator de escala atual (zoom)
 * Valor padrão: 1 (100%)
 */
const scale = computed(() => editor.scaleFactor.value || 1);

/**
 * Display formatado da escala (ex: "2.5x")
 */
const scaleDisplay = computed(() => `${scale.value.toFixed(1)}x`);

/**
 * Indica se pode desfazer (há itens no histórico)
 */
const canUndo = computed(() => editor.canUndo.value);

/**
 * Indica se pode refazer (há itens no histórico de redo)
 */
const canRedo = computed(() => editor.canRedo.value);

/**
 * Indica se há mudanças não salvas
 */
const hasChanges = computed(() => editor.hasChanges.value);

/**
 * Contador de mudanças pendentes
 */
const changeCount = computed(() => editor.changeCount.value);

/**
 * Indica se está salvando no momento
 */
const isSaving = computed(() => editor.isSaving.value);

/**
 * Indica se auto-save está habilitado
 */
const autoSaveEnabled = computed(() => changes.autoSaveEnabled.value);

// ============================================================================
// LOCAL STATE
// ============================================================================

/**
 * Alinhamento atual selecionado nas ferramentas
 * TODO: Mover para composable se precisar ser global
 */
const alignment = computed<'left' | 'right' | 'center' | 'justify' | undefined>(
    () => editor.currentGondola.value?.alignment || undefined,
);

/**
 * Estado da modal de confirmação de delete
 */
const showDeleteConfirmation = computed({
    get: () => editor.showDeleteConfirmation.value,
    set: (val) => (editor.showDeleteConfirmation.value = val),
});

/**
 * Nome da gôndola atual para o dialog
 */
const currentGondolaName = computed(
    () => editor.currentGondola.value?.name || '',
);

/**
 * Estado do drawer de adicionar módulo
 */
const showAddModuleDrawer = computed({
    get: () => editor.showAddModuleDrawer.value,
    set: (val) => (editor.showAddModuleDrawer.value = val),
});

/**
 * ID da gôndola atual para adicionar módulo
 */
const gondolaId = computed(() => editor.currentGondola.value?.id);

/**
 * Altura da gôndola atual
 */
const gondolaHeight = computed(
    () => editor.currentGondola.value?.height ?? 200,
);

/**
 * Seções da gôndola atual
 */
const sections = computed(() => editor.currentGondola.value?.sections ?? []);

/**
 * Feature flag: Geração Automática habilitada?
 */
const autoGenerateEnabled = computed(
    () => (page.props.features as any)?.auto_generate ?? false,
);

const aiModelOptions = computed(
    () => (page.props as any)?.aiModelOptions ?? [],
);

const strategyOptions = computed(
    () => (page.props as any)?.strategyOptions ?? [],
);

/**
 * Análises da gôndola (ABC e Stock)
 */
const analysis = computed(() => (page.props as any)?.analysis ?? {});

const permissions = computed(
    () =>
        (page.props as any)?.permissions ?? {
            can_create_gondola: false,
            can_update_gondola: false,
            can_remove_gondola: false,
            can_autogenate_gondola: true,
        },
);

/**
 * Estado do modal de geração automática
 */
const showAutoGenerateModal = ref(false);

/**
 * Estado do modal de compartilhamento/QR code
 */
// const _showShareQRModal = ref(false);

/**
 * Estado do modal de transferência de seção
 */
const showTransferSectionDialog = ref(false);

/**
 * Seção selecionada (se houver)
 */
const selectedSection = computed(() => {
    const item = selection.selectedItem.value;
    if (item?.type === 'section') {
        return item.item as any; // Type assertion para compatibilidade com tipos readonly
    }
    return null;
});

/**
 * Estado do modal de seleção de região do mapa
 */
const showMapRegionSelector = ref(false);

/**
 * Verifica se o planograma tem loja associada (store_id)
 */
const hasStore = computed(() => {
    const planogram = currentGondola.value?.planogram as any;
    return !!planogram?.store_id;
});

/**
 * Dados da loja para o mapa
 */
const storeData = computed(() => {
    const planogram = currentGondola.value?.planogram as any;
    return planogram?.store || null;
});

/**
 * URL da imagem do mapa da loja
 */
const mapImageUrl = computed(() => {
    const store = storeData.value;
    if (!store?.map_image_path) return null;
    // Retorna a URL pública do storage
    return `/storage/${store.map_image_path}`;
});

/**
 * Regiões do mapa da loja
 */
const mapRegions = computed(() => {
    const store = storeData.value;
    return store?.map_regions || [];
});

/**
 * Busca por EAN para localizar produto na gondola
 */
const eanSearchModel = computed({
    get: () => eanSearchQuery.value,
    set: (value: string) => {
        eanSearchQuery.value = value.replace(/\D/g, '');
    },
});

const syncingEanFromSelection = ref(false);

watch(
    () => selection.selectedItem.value,
    (selected) => {
        if (!selected || selected.type !== 'segment') {
            return;
        }

        const selectedSegment = selected.item as any;
        const selectedEan = String(
            selectedSegment?.layer?.product?.ean ?? '',
        ).replace(/\D/g, '');

        if (!selectedEan || selectedEan === eanSearchQuery.value) {
            return;
        }

        syncingEanFromSelection.value = true;
        eanSearchQuery.value = selectedEan;
    },
);

watch(
    () => eanSearchQuery.value,
    (query) => {
        if (syncingEanFromSelection.value) {
            syncingEanFromSelection.value = false;
            return;
        }

        const normalizedQuery = query.trim();
        const gondola = currentGondola.value;

        if (!normalizedQuery || !gondola?.sections) {
            return;
        }

        for (const section of gondola.sections) {
            if (!section?.shelves) {
                continue;
            }

            for (const shelf of section.shelves) {
                if (!shelf?.segments) {
                    continue;
                }

                for (const segment of shelf.segments) {
                    const productEan = String(
                        segment?.layer?.product?.ean ?? '',
                    ).trim();

                    if (!segment?.id || !productEan.includes(normalizedQuery)) {
                        continue;
                    }

                    selection.selectItem('segment', segment.id, segment, {
                        shelf,
                    });

                    return;
                }
            }
        }
    },
);

/**
 * ID da região vinculada à gôndola atual
 */
const currentMapRegionId = computed(() => {
    return currentGondola.value?.linked_map_gondola_id || null;
});

/**
 * Handler para quando uma região é selecionada
 */
const handleMapRegionSelect = (regionId: string | null) => {
    if (!currentGondola.value) return;

    // Busca a região para obter o tipo
    const region = mapRegions.value.find((r: any) => r.id === regionId);
    // Atualiza a gôndola com linked_map_gondola_id e category
    editor.updateGondola({
        linked_map_gondola_id: regionId,
        linked_map_gondola_category: region?.type || null,
    });
};
</script>

<template>
    <!-- ========================================================================
       TOOLBAR PRINCIPAL
       Barra de ferramentas com controles de edição, zoom, alinhamento
       ======================================================================== -->
    <div class="border-b bg-muted/50">
        <div class="space-y-4 p-4">
            <!-- ==================================================================
           NAVEGAÇÃO ENTRE GÔNDOLAS
           Tabs clicáveis para trocar de gôndola dentro do planograma
           ================================================================== -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                <Link
                    v-for="gondola in gondolas"
                    :key="gondola.id"
                    :href="gondola.route_gondolas ?? ''"
                    :class="[
                        'inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-all',
                        'hover:bg-accent hover:text-accent-foreground',
                        gondola.id === currentGondolaId
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground',
                    ]"
                >
                    {{ gondola.name }}
                </Link>
            </div>

            <!-- ==================================================================
           CONTROLES E FERRAMENTAS
           Organizados em grupos: Zoom, Alinhamento, Ações, Histórico, Salvar
           ================================================================== -->
            <div class="flex flex-wrap items-center gap-2" data-toolbar>
                <!-- ============================================================
             CONTROLES DE ZOOM/ESCALA
             Diminuir, Input (readonly), Aumentar
             ============================================================ -->
                <div
                    class="flex items-center gap-1 rounded-md border bg-background p-1"
                >
                    <ButtonWithTooltip
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        tooltip="Diminuir zoom"
                        @click="editor.decreaseScale()"
                    >
                        <Minus class="size-4" />
                    </ButtonWithTooltip>

                    <Input
                        :model-value="scaleDisplay"
                        class="h-7 w-14 text-center text-xs"
                        readonly
                    />

                    <ButtonWithTooltip
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        tooltip="Aumentar zoom"
                        @click="editor.increaseScale()"
                    >
                        <Plus class="size-4" />
                    </ButtonWithTooltip>
                </div>

                <div
                    class="flex items-center gap-1 rounded-md border bg-background px-2 py-1"
                >
                    <Search class="size-3.5 text-muted-foreground" />
                    <Input
                        v-model="eanSearchModel"
                        placeholder="Buscar EAN na gondola"
                        class="h-7 w-40 border-0 px-1 text-xs shadow-none focus-visible:ring-0"
                    />
                    <ButtonWithTooltip
                        v-if="eanSearchModel"
                        variant="ghost"
                        size="icon"
                        class="size-6"
                        tooltip="Limpar busca por EAN"
                        @click="eanSearchModel = ''"
                    >
                        <X class="size-3.5" />
                    </ButtonWithTooltip>
                </div>

                <Separator orientation="vertical" class="h-8" />

                <!-- ============================================================
             FERRAMENTAS DE ALINHAMENTO E GRADE
             Grade, Alinhar Esquerda/Direita/Centro, Justificar
             ============================================================ -->
                <ButtonWithTooltip
                    :variant="editor.showGrid.value ? 'default' : 'outline'"
                    size="sm"
                    tooltip="Exibir/ocultar grade"
                    @click="editor.toggleGrid()"
                >
                    <Grid3x3 class="mr-2 size-4" />
                    Grade
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    :variant="alignment === 'left' ? 'default' : 'outline'"
                    size="sm"
                    tooltip="Alinhar produtos à esquerda"
                    @click="editor.alignLeft()"
                >
                    <AlignLeft class="size-4" />
                    <span class="sr-only">Alinhar esquerda</span>
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    :variant="alignment === 'center' ? 'default' : 'outline'"
                    size="sm"
                    tooltip="Centralizar produtos"
                    @click="editor.alignCenter()"
                >
                    <AlignCenter class="size-4" />
                    <span class="sr-only">Centralizar</span>
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    :variant="alignment === 'right' ? 'default' : 'outline'"
                    size="sm"
                    tooltip="Alinhar produtos à direita"
                    @click="editor.alignRight()"
                >
                    <AlignRight class="size-4" />
                    <span class="sr-only">Alinhar direita</span>
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    :variant="alignment === 'justify' ? 'default' : 'outline'"
                    size="sm"
                    tooltip="Distribuir produtos uniformemente"
                    @click="editor.alignJustify()"
                >
                    <AlignHorizontalDistributeCenter class="size-4" />
                    <span class="sr-only">Justificar</span>
                </ButtonWithTooltip>

                <Separator orientation="vertical" class="h-8" />

                <!-- ============================================================
             AÇÕES DE EDIÇÃO
             Inverter, Adicionar Módulo, Remover Gôndola
             ============================================================ -->
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    tooltip="Inverter ordem dos elementos"
                    @click="editor.toggleFlow()"
                >
                    <FlipHorizontal class="mr-2 size-4" />
                    Inverter
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    tooltip="Adicionar novo módulo à gôndola"
                    @click="editor.addModule()"
                >
                    <Plus class="mr-2 size-4" />
                    <span class="max-w-24 truncate">Adicionar Módulo</span>
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    tooltip="Transferir seção entre gôndolas"
                    @click="showTransferSectionDialog = true"
                >
                    <ArrowRightLeft class="mr-2 size-4" />
                    <span class="max-w-24 truncate">Transferir Seção</span>
                </ButtonWithTooltip>

                <!-- Vincular ao Mapa (apenas se houver loja) -->
                <ButtonWithTooltip
                    v-if="hasStore"
                    :variant="currentMapRegionId ? 'default' : 'outline'"
                    size="sm"
                    tooltip="Vincular gôndola a uma posição no mapa da loja"
                    @click="showMapRegionSelector = true"
                >
                    <MapPin class="mr-2 size-4" />
                    <span class="max-w-24 truncate">{{
                        currentMapRegionId ? 'Remover do Mapa' : 'Mapa da Loja'
                    }}</span>
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    v-if="permissions.can_remove_gondola"
                    variant="destructive"
                    size="sm"
                    :tooltip="
                        currentMapRegionId
                            ? 'Remover gôndola selecionada'
                            : 'Nenhuma gôndola selecionada para remoção'
                    "
                    @click="editor.removeGondola()"
                >
                    <Trash2 class="mr-2 size-4" />
                    <span class="max-w-24 truncate">Remover Gôndola</span>
                </ButtonWithTooltip>

                <Separator orientation="vertical" class="h-8" />

                <!-- ============================================================
             HISTÓRICO (UNDO/REDO)
             Integrado com usePlanogramHistory composable
             ============================================================ -->
                <ButtonWithTooltip
                    variant="outline"
                    size="icon"
                    :disabled="!canUndo"
                    tooltip="Desfazer (Ctrl+Z)"
                    @click="editor.undo()"
                >
                    <Undo2 class="size-4" />
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    variant="outline"
                    size="icon"
                    :disabled="!canRedo"
                    tooltip="Refazer (Ctrl+Shift+Z ou Ctrl+Y)"
                    @click="editor.redo()"
                >
                    <Redo2 class="size-4" />
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    variant="outline"
                    size="icon"
                    :disabled="!canUndo && !canRedo"
                    tooltip="Limpar Histórico"
                    @click="editor.clearHistory()"
                >
                    <Trash2 class="size-4" />
                </ButtonWithTooltip>

                <Separator orientation="vertical" class="h-8" />

                <!-- ============================================================
             AÇÕES FINAIS
             Auto-save, Salvar (com contador de mudanças), Performance, Imprimir, Relatórios
             ============================================================ -->

                <!-- Toggle Auto-save -->
                <div
                    class="flex items-center gap-2 rounded-md border bg-background px-3 py-1.5"
                >
                    <Switch
                        :id="'auto-save-toggle'"
                        v-model="autoSaveEnabled"
                        @update:model-value="changes.toggleAutoSave()"
                    />
                    <Label
                        :for="'auto-save-toggle'"
                        class="cursor-pointer text-xs font-medium"
                    >
                        Salvamento automático
                    </Label>
                </div>

                <ButtonWithTooltip
                    variant="default"
                    size="sm"
                    :disabled="!hasChanges || isSaving"
                    :tooltip="
                        hasChanges
                            ? `Salvar ${changeCount} alteraç${
                                  changeCount === 1 ? 'ão' : 'ões'
                              } (Ctrl+S)`
                            : 'Nenhuma alteração para salvar'
                    "
                    @click="editor.save()"
                >
                    <Save
                        class="mr-2 size-4"
                        :class="{ 'animate-pulse': isSaving }"
                    />
                    <span v-if="isSaving">Salvando...</span>
                    <span v-else-if="hasChanges"
                        >Salvar ({{ changeCount }})</span
                    >
                    <span v-else>Salvo</span>
                </ButtonWithTooltip>

                <!-- Indicadores de Análises -->

                <!-- Dropdown Performance -->
                <DropdownPerformance
                    :analysis="analysis"
                    :gondola="currentGondola as Gondola"
                />

                <Performance
                    :open="showPerformanceModal"
                    :gondola-id="currentGondolaId"
                    :planogram="
                        currentGondola?.planogram
                            ? (currentGondola.planogram as any)
                            : null
                    "
                    @update:open="
                        (value: boolean) => (showPerformanceModal = value)
                    "
                />

                <!-- Geração Automática (Feature Flag) -->
                <ButtonWithTooltip
                    v-if="autoGenerateEnabled && (permissions.can_autogenate_gondola || permissions.can_autogenate_gondola_ia)"
                    variant="default"
                    size="sm"
                    tooltip="Gerar planograma automaticamente com IA"
                    @click="showAutoGenerateModal = true"
                >
                    <Sparkles class="mr-2 size-4" />
                    <span class="max-w-24 truncate">
                        Gerar Automaticamente</span
                    >
                </ButtonWithTooltip>
                <AutoGenerateModal
                    v-if="autoGenerateEnabled && (permissions.can_autogenate_gondola || permissions.can_autogenate_gondola_ia) "
                    :open="showAutoGenerateModal"
                    :category-id="
                        (currentGondola?.planogram as any).category_id
                    "
                    :gondola-id="currentGondola?.id || ''"
                    :start-date="(currentGondola?.planogram as any)?.start_date"
                    :end-date="(currentGondola?.planogram as any)?.end_date"
                    :ai-model-options="aiModelOptions"
                    :strategy-options="strategyOptions"
                    :permissions="permissions"
                    @update:open="
                        (value: boolean) => (showAutoGenerateModal = value)
                    "
                />

                <!-- Dropdown Ações -->
                <DropdownActions />
            </div>
        </div>

        <!-- ============================================================
         MODAL DE CONFIRMAÇÃO DE REMOÇÃO DE GÔNDOLA
         ============================================================ -->
        <ConfirmDeleteGondolaDialog
            v-model:open="showDeleteConfirmation"
            :gondola-name="currentGondolaName"
            @confirm="editor.confirmRemoveGondola()"
        />

        <!-- ============================================================
         SHEET DE ADICIONAR MÓDULO
         ============================================================ -->
        <AddModuleSheet
            v-model:open="showAddModuleDrawer"
            :gondola-id="gondolaId"
            :gondola-height="gondolaHeight"
            :sections="sections"
            @success="editor.handleModuleAdded($event)"
        />

        <!-- ============================================================
         MODAL DE TRANSFERÊNCIA DE SEÇÃO
         ============================================================ -->
        <TransferSectionDialog
            v-model:open="showTransferSectionDialog"
            :section="selectedSection"
        />

        <!-- ============================================================
         MODAL DE SELEÇÃO DE REGIÃO DO MAPA
         ============================================================ -->
        <MapRegionSelectorModal
            v-model:open="showMapRegionSelector"
            :store-id="storeData?.id"
            :store-name="storeData?.name"
            :map-image-url="mapImageUrl"
            :map-regions="mapRegions"
            :current-region-id="currentMapRegionId"
            :gondola-id="currentGondola?.id"
            :gondola-name="currentGondola?.name"
            @select="handleMapRegionSelect"
        />
    </div>
</template>
