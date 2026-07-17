<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.sidebar.transfer_section.title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('plannerate.sidebar.transfer_section.description') }}
                </DialogDescription>
            </DialogHeader>

            <!-- Contexto: gôndola atual (origem/destino conforme o modo) -->
            <div class="flex items-center gap-2 rounded-md bg-muted/40 px-3 py-2 text-xs">
                <LayoutGrid class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <span class="text-muted-foreground">{{ t('plannerate.sidebar.transfer_section.from_label') }}:</span>
                <span class="truncate font-medium text-foreground">
                    {{ currentGondola?.name ?? t('plannerate.sidebar.transfer_section.current_gondola') }}
                </span>
            </div>

            <Tabs v-model="operationMode" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="send">
                        <ArrowRight class="mr-2 h-4 w-4" />
                        {{ t('plannerate.sidebar.transfer_section.send') }}
                    </TabsTrigger>
                    <TabsTrigger value="receive">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ t('plannerate.sidebar.transfer_section.receive') }}
                    </TabsTrigger>
                </TabsList>

                <!-- Modo: Enviar Módulo -->
                <TabsContent value="send" class="space-y-4 py-4">
                    <div class="rounded-lg border bg-muted/50 p-3">
                        <p class="text-sm font-medium">
                            {{ section ? t('plannerate.sidebar.transfer_section.send_section_named', { name: section.name }) : t('plannerate.sidebar.transfer_section.send_section') }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ section ? t('plannerate.sidebar.transfer_section.send_section_hint') : t('plannerate.sidebar.transfer_section.select_section_to_send') }}
                        </p>
                    </div>

                    <!-- Select de Módulo (quando não há módulo pré-selecionado) -->
                    <div v-if="!section" class="space-y-2">
                        <Label for="send-section-select">{{ t('plannerate.sidebar.transfer_section.section') }}</Label>
                        <Select v-model="selectedSectionToSend" :disabled="isLoadingCurrentSections">
                            <SelectTrigger id="send-section-select" class="h-9 w-full">
                                <SelectValue :placeholder="t('plannerate.sidebar.transfer_section.select_section')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="sectionItem in currentGondolaSections"
                                    :key="sectionItem.id"
                                    :value="sectionItem.id"
                                >
                                    {{ sectionItem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="isLoadingCurrentSections" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Loader2 class="h-3.5 w-3.5 animate-spin" />
                            {{ t('plannerate.sidebar.transfer_section.loading_sections') }}
                        </p>
                    </div>

                    <!-- Select de Planograma -->
                    <div class="space-y-2">
                        <Label for="send-planogram-select">{{ t('plannerate.sidebar.transfer_section.planogram') }}</Label>
                        <Select v-model="selectedPlanogramId" @update:model-value="handlePlanogramChange">
                            <SelectTrigger id="send-planogram-select" class="h-9 w-full">
                                <SelectValue :placeholder="t('plannerate.sidebar.transfer_section.select_planogram')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="planogram in planograms"
                                    :key="planogram.id"
                                    :value="planogram.id"
                                >
                                    {{ planogram.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Select de Gôndola -->
                    <div class="space-y-2">
                        <Label for="send-gondola-select">{{ t('plannerate.sidebar.transfer_section.gondola') }}</Label>
                        <Select v-model="selectedGondolaId" :disabled="!selectedPlanogramId || isLoadingGondolas">
                            <SelectTrigger id="send-gondola-select" class="h-9 w-full">
                                <SelectValue :placeholder="t('plannerate.sidebar.transfer_section.select_gondola')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="gondola in gondolas"
                                    :key="gondola.id"
                                    :value="gondola.id"
                                >
                                    {{ gondola.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="isLoadingGondolas" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Loader2 class="h-3.5 w-3.5 animate-spin" />
                            {{ t('plannerate.sidebar.transfer_section.loading_gondolas') }}
                        </p>
                    </div>

                    <!-- Informações adicionais -->
                    <div v-if="isSameGondola" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p class="text-sm text-amber-900 dark:text-amber-100">
                            {{ t('plannerate.sidebar.transfer_section.warning_already_in_gondola') }}
                        </p>
                    </div>
                    <div v-else-if="selectedGondolaId" class="flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                        <Info class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                        <p class="text-sm text-blue-900 dark:text-blue-100">
                            {{ t('plannerate.sidebar.transfer_section.info_move_to_end') }}
                        </p>
                    </div>

                    <Button
                        @click="handleTransfer"
                        :disabled="!selectedGondolaId || isTransferring || isSameGondola || (!section && !selectedSectionToSend)"
                        class="w-full"
                    >
                        <Loader2 v-if="isTransferring" class="mr-2 h-4 w-4 animate-spin" />
                        <ArrowRight v-else class="mr-2 h-4 w-4" />
                        {{ sendButtonLabel }}
                    </Button>
                </TabsContent>

                <!-- Modo: Trazer Módulo -->
                <TabsContent value="receive" class="space-y-4 py-4">
                    <div class="rounded-lg border bg-muted/50 p-3">
                        <p class="text-sm font-medium">{{ t('plannerate.sidebar.transfer_section.receive_from_other') }}</p>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ t('plannerate.sidebar.transfer_section.receive_hint') }}
                        </p>
                    </div>

                    <!-- Select de Planograma -->
                    <div class="space-y-2">
                        <Label for="receive-planogram-select">{{ t('plannerate.sidebar.transfer_section.planogram') }}</Label>
                        <Select v-model="receivePlanogramId" @update:model-value="handleReceivePlanogramChange">
                            <SelectTrigger id="receive-planogram-select" class="h-9 w-full">
                                <SelectValue :placeholder="t('plannerate.sidebar.transfer_section.select_planogram')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="planogram in planograms"
                                    :key="planogram.id"
                                    :value="planogram.id"
                                >
                                    {{ planogram.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Select de Gôndola -->
                    <div class="space-y-2">
                        <Label for="receive-gondola-select">{{ t('plannerate.sidebar.transfer_section.gondola') }}</Label>
                        <Select
                            v-model="receiveGondolaId"
                            @update:model-value="handleReceiveGondolaChange"
                            :disabled="!receivePlanogramId || isLoadingReceiveGondolas"
                        >
                            <SelectTrigger id="receive-gondola-select" class="h-9 w-full">
                                <SelectValue :placeholder="t('plannerate.sidebar.transfer_section.select_gondola')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="gondola in receiveGondolas"
                                    :key="gondola.id"
                                    :value="gondola.id"
                                >
                                    {{ gondola.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="isLoadingReceiveGondolas" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Loader2 class="h-3.5 w-3.5 animate-spin" />
                            {{ t('plannerate.sidebar.transfer_section.loading_gondolas') }}
                        </p>
                    </div>

                    <!-- Select de Módulo -->
                    <div class="space-y-2">
                        <Label for="receive-section-select">{{ t('plannerate.sidebar.transfer_section.section') }}</Label>
                        <Select v-model="selectedSectionId" :disabled="!receiveGondolaId || isLoadingSections">
                            <SelectTrigger id="receive-section-select" class="h-9 w-full">
                                <SelectValue :placeholder="t('plannerate.sidebar.transfer_section.select_section')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="sectionItem in availableSections"
                                    :key="sectionItem.id"
                                    :value="sectionItem.id"
                                >
                                    {{ sectionItem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="isLoadingSections" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Loader2 class="h-3.5 w-3.5 animate-spin" />
                            {{ t('plannerate.sidebar.transfer_section.loading_sections') }}
                        </p>
                    </div>

                    <!-- Informações adicionais -->
                    <div v-if="isCurrentGondola" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p class="text-sm text-amber-900 dark:text-amber-100">
                            {{ t('plannerate.sidebar.transfer_section.warning_already_in_gondola') }}
                        </p>
                    </div>
                    <div v-else-if="selectedSectionId" class="flex items-start gap-2 rounded-lg border border-green-200 bg-green-50/50 p-3 dark:border-green-800 dark:bg-green-950/20">
                        <Info class="mt-0.5 h-4 w-4 shrink-0 text-green-600 dark:text-green-400" />
                        <p class="text-sm text-green-900 dark:text-green-100">
                            {{ t('plannerate.sidebar.transfer_section.info_receive_to_end') }}
                        </p>
                    </div>

                    <Button
                        @click="handleReceive"
                        :disabled="!selectedSectionId || isTransferring || isCurrentGondola"
                        class="w-full"
                    >
                        <Loader2 v-if="isTransferring" class="mr-2 h-4 w-4 animate-spin" />
                        <ArrowLeft v-else class="mr-2 h-4 w-4" />
                        {{ receiveButtonLabel }}
                    </Button>
                </TabsContent>
            </Tabs>

            <!-- Histórico de Operações -->
            <div class="mt-4">
                <Separator class="mb-4" />
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <History class="h-4 w-4 text-muted-foreground" />
                        <h3 class="text-sm font-medium">{{ t('plannerate.sidebar.transfer_section.history_title') }}</h3>
                        <Badge v-if="operationHistory.length > 0" variant="secondary" class="ml-1">
                            {{ operationHistory.length }}
                        </Badge>
                    </div>
                    <Button
                        v-if="operationHistory.length > 0"
                        variant="ghost"
                        size="sm"
                        @click="clearHistoryDialogOpen = true"
                        :disabled="isReverting"
                        class="h-7 text-xs text-muted-foreground hover:text-destructive"
                    >
                        <Trash2 class="mr-1 h-3 w-3" />
                        {{ t('plannerate.sidebar.transfer_section.clear') }}
                    </Button>
                </div>
                <div v-if="operationHistory.length > 0" class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    <div
                        v-for="(operation) in operationHistory"
                        :key="operation.id"
                        class="group relative flex items-start justify-between rounded-lg border bg-muted/30 p-3 text-sm transition-all hover:bg-muted/50"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <div
                                    :class="[
                                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full',
                                        operation.type === 'send'
                                            ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
                                    ]"
                                >
                                    <component
                                        :is="operation.type === 'send' ? ArrowRight : ArrowLeft"
                                        class="h-3.5 w-3.5"
                                    />
                                </div>
                                <span class="truncate font-medium">{{ operation.sectionName }}</span>
                            </div>
                            <p class="mt-1.5 text-xs text-muted-foreground">
                                {{ operation.description }}
                            </p>
                            <div class="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Clock class="h-3 w-3 shrink-0" />
                                <span>{{ formatTime(operation.timestamp) }}</span>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="revertOperation(operation)"
                            :disabled="isReverting || revertingOperationId === operation.id"
                            class="ml-3 h-7 shrink-0"
                        >
                            <RotateCcw
                                v-if="revertingOperationId !== operation.id"
                                class="mr-1 h-3 w-3"
                            />
                            <Loader2
                                v-else
                                class="mr-1 h-3 w-3 animate-spin"
                            />
                            {{ t('plannerate.sidebar.transfer_section.revert') }}
                        </Button>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed bg-muted/20 p-6 text-center">
                    <History class="mx-auto h-8 w-8 text-muted-foreground/50" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('plannerate.sidebar.transfer_section.no_operations') }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground/70">
                        {{ t('plannerate.sidebar.transfer_section.operations_will_appear') }}
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleClose">
                    {{ t('plannerate.sidebar.transfer_section.close') }}
                </Button>
            </DialogFooter>

            <!-- Confirmação de limpar histórico -->
            <AlertDialog v-model:open="clearHistoryDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('plannerate.sidebar.transfer_section.clear_history_title') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {{ t('plannerate.sidebar.transfer_section.clear_history_description') }}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{{ t('plannerate.sidebar.transfer_section.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            @click="confirmClearHistory"
                        >
                            {{ t('plannerate.sidebar.transfer_section.clear_confirm') }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ArrowRight, Clock, History, Info, LayoutGrid, Loader2, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { sections as gondolaSectionsRoute } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/GondolaController';
import { gondolas as planogramGondolasRoute, index as planogramsIndexRoute } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/PlanogramApiController';
import { transfer } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/SectionController';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { useT } from '@/composables/useT';
import type { Section } from '@/types/planogram';

// Interface para histórico de operações
interface OperationHistory {
    id: string;
    type: 'send' | 'receive';
    sectionId: string;
    sectionName: string;
    fromGondolaId: string;
    fromGondolaName?: string;
    toGondolaId: string;
    toGondolaName?: string;
    timestamp: number;
    description: string;
}

interface SelectOption {
    id: string;
    name: string;
}

/** Formato das collections da API do editor (Laravel Resource: { data: [...] }). */
type ApiCollection<T> = { data?: T[] };

interface Props {
    open?: boolean;
    section: Section | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
});

const emit = defineEmits<Emits>();
const { t } = useT();

const editor = usePlanogramEditor();

// Estado local
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

// Modo de operação: 'send' (enviar) ou 'receive' (trazer)
const operationMode = ref<'send' | 'receive'>('send');

// Estado para modo "Enviar"
const selectedPlanogramId = ref<string>('');
const selectedGondolaId = ref<string>('');
const selectedSectionToSend = ref<string>(''); // Módulo selecionado quando não há módulo pré-selecionado
const currentGondolaSections = ref<SelectOption[]>([]); // Modulos da gôndola atual
const isLoadingCurrentSections = ref(false);
const planograms = ref<SelectOption[]>([]);
const gondolas = ref<SelectOption[]>([]);
const isLoadingGondolas = ref(false);

// Estado para modo "Trazer"
const receivePlanogramId = ref<string>('');
const receiveGondolaId = ref<string>('');
const receiveGondolas = ref<SelectOption[]>([]);
const selectedSectionId = ref<string>('');
const availableSections = ref<SelectOption[]>([]);
const isLoadingReceiveGondolas = ref(false);
const isLoadingSections = ref(false);

// Estado compartilhado
const isTransferring = ref(false);
const isReverting = ref(false);
const revertingOperationId = ref<string | null>(null);
const clearHistoryDialogOpen = ref(false);

// Requisições standalone ao backend próprio via useHttp (Inertia v3 — sem axios)
const planogramsHttp = useHttp<Record<string, never>, ApiCollection<SelectOption>>();
const sendGondolasHttp = useHttp<Record<string, never>, ApiCollection<SelectOption>>();
const sendSectionsHttp = useHttp<Record<string, never>, ApiCollection<SelectOption>>();
const receiveGondolasHttp = useHttp<Record<string, never>, ApiCollection<SelectOption>>();
const receiveSectionsHttp = useHttp<Record<string, never>, ApiCollection<SelectOption>>();

// Chave para localStorage
const STORAGE_KEY = 'transfer_section_history';
const isBrowser = typeof window !== 'undefined';

// Histórico de operações (carrega do localStorage)
const operationHistory = ref<OperationHistory[]>(loadHistoryFromStorage());

/**
 * Carrega histórico do localStorage
 */
function loadHistoryFromStorage(): OperationHistory[] {
    if (!isBrowser) {
        return [];
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (stored) {
            const parsed = JSON.parse(stored) as OperationHistory[];
            // Filtra operações muito antigas (mais de 7 dias)
            const sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
            const filtered = parsed.filter(op => op.timestamp > sevenDaysAgo);

            // Se houve filtragem, salva novamente
            if (filtered.length !== parsed.length) {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(filtered));
            }

            return filtered;
        }
    } catch (error) {
        console.warn('Erro ao carregar histórico do localStorage:', error);

        // Se houver erro, limpa o localStorage corrompido
        try {
            window.localStorage.removeItem(STORAGE_KEY);
        } catch (cleanError) {
            console.error('Erro ao limpar localStorage corrompido:', cleanError);
        }
    }

    return [];
}

/**
 * Salva histórico no localStorage
 */
function saveHistoryToStorage() {
    if (!isBrowser) {
        return;
    }

    try {
        // Limita a 50 operações mais recentes para evitar localStorage cheio
        const historyToSave = operationHistory.value.slice(0, 50);
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(historyToSave));

        // Se foi limitado, atualiza o ref
        if (historyToSave.length < operationHistory.value.length) {
            operationHistory.value = historyToSave;
        }
    } catch (error) {
        console.warn('Erro ao salvar histórico no localStorage:', error);

        // Se o localStorage estiver cheio, remove as operações mais antigas
        if (error instanceof DOMException && (error.code === 22 || error.name === 'QuotaExceededError')) {
            // Remove 50% das operações mais antigas
            const sorted = [...operationHistory.value].sort((a, b) => a.timestamp - b.timestamp);
            const reduced = sorted.slice(Math.floor(sorted.length / 2));
            operationHistory.value = reduced;

            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(reduced));
            } catch (retryError) {
                console.error('Erro ao salvar histórico após limpeza:', retryError);
                // Se ainda falhar, limpa tudo
                operationHistory.value = [];
                window.localStorage.removeItem(STORAGE_KEY);
            }
        }
    }
}

// Busca gôndola e planograma atuais
const currentGondola = computed(() => editor.currentGondola.value);

// Verifica se é a mesma gôndola (modo Enviar)
const isSameGondola = computed(() => {
    return selectedGondolaId.value === currentGondola.value?.id;
});

// Verifica se é a gôndola atual (modo Trazer)
const isCurrentGondola = computed(() => {
    return receiveGondolaId.value === currentGondola.value?.id;
});

// Rótulos dos botões
const sendButtonLabel = computed(() => {
    return isSameGondola.value
        ? t('plannerate.sidebar.transfer_section.same_gondola')
        : t('plannerate.sidebar.transfer_section.move_section');
});

const receiveButtonLabel = computed(() => {
    return isCurrentGondola.value
        ? t('plannerate.sidebar.transfer_section.same_gondola')
        : t('plannerate.sidebar.transfer_section.receive_section');
});

// Inicializa quando abre o dialog
watch(() => props.open, async (isOpen) => {
    if (isOpen) {
        // Recarrega histórico do localStorage (caso tenha sido atualizado em outra aba)
        operationHistory.value = loadHistoryFromStorage();

        // Carrega lista de planogramas
        await loadPlanograms();

        // Define planograma e gôndola atuais como padrão (modo Enviar)
        if (currentGondola.value) {
            selectedPlanogramId.value = currentGondola.value.planogram_id || '';
            selectedGondolaId.value = currentGondola.value.id;

            // Carrega gondolas do planograma atual
            await loadGondolas(currentGondola.value.planogram_id || '');

            // Se não há módulo pré-selecionado, carrega módulos da gôndola atual
            if (!props.section && currentGondola.value.id) {
                await loadCurrentGondolaSections(currentGondola.value.id);
            }
        }

        // Reseta modo Trazer
        receivePlanogramId.value = '';
        receiveGondolaId.value = '';
        selectedSectionId.value = '';
        receiveGondolas.value = [];
        availableSections.value = [];

        // Reseta módulo selecionado para enviar
        selectedSectionToSend.value = '';
    }
});

// Reseta seleções quando muda o modo de operação
watch(operationMode, () => {
    if (operationMode.value === 'send') {
        // Reseta modo Trazer
        receivePlanogramId.value = '';
        receiveGondolaId.value = '';
        selectedSectionId.value = '';
        receiveGondolas.value = [];
        availableSections.value = [];
    } else {
        // Reseta modo Enviar
        selectedPlanogramId.value = '';
        selectedGondolaId.value = '';
        gondolas.value = [];
    }
});

/**
 * Carrega lista de planogramas disponíveis
 */
async function loadPlanograms() {
    try {
        await planogramsHttp.get(planogramsIndexRoute.url());
        planograms.value = planogramsHttp.response?.data ?? [];
    } catch (error) {
        console.error('Erro ao carregar planogramas:', error);
        toast.error(t('plannerate.sidebar.transfer_section.errors.load_planograms'));
        planograms.value = [];
    }
}

/**
 * Carrega gôndolas do planograma selecionado
 */
async function loadGondolas(planogramId: string) {
    if (!planogramId) {
        gondolas.value = [];

        return;
    }

    isLoadingGondolas.value = true;

    try {
        await sendGondolasHttp.get(planogramGondolasRoute.url({ planogram: planogramId }));
        gondolas.value = sendGondolasHttp.response?.data ?? [];
    } catch (error) {
        console.error('Erro ao carregar gôndolas:', error);
        toast.error(t('plannerate.sidebar.transfer_section.errors.load_gondolas'));
        gondolas.value = [];
    } finally {
        isLoadingGondolas.value = false;
    }
}

/**
 * Carrega módulos da gôndola atual (para seleção quando não há módulo pré-selecionado)
 */
async function loadCurrentGondolaSections(gondolaId: string) {
    if (!gondolaId) {
        currentGondolaSections.value = [];

        return;
    }

    isLoadingCurrentSections.value = true;

    try {
        await sendSectionsHttp.get(gondolaSectionsRoute.url({ gondola: gondolaId }));
        currentGondolaSections.value = sendSectionsHttp.response?.data ?? [];
    } catch (error) {
        console.error('Erro ao carregar módulos da gôndola:', error);
        toast.error(t('plannerate.sidebar.transfer_section.errors.load_sections'));
        currentGondolaSections.value = [];
    } finally {
        isLoadingCurrentSections.value = false;
    }
}

/**
 * Handler para mudança de planograma (modo Enviar)
 */
async function handlePlanogramChange() {
    selectedGondolaId.value = '';
    await loadGondolas(selectedPlanogramId.value);
}

/**
 * Handler para mudança de planograma (modo Trazer)
 */
async function handleReceivePlanogramChange() {
    receiveGondolaId.value = '';
    selectedSectionId.value = '';
    availableSections.value = [];
    await loadReceiveGondolas(receivePlanogramId.value);
}

/**
 * Handler para mudança de gôndola (modo Trazer)
 */
async function handleReceiveGondolaChange() {
    selectedSectionId.value = '';
    await loadSections(receiveGondolaId.value);
}

/**
 * Carrega gôndolas do planograma selecionado (modo Trazer)
 */
async function loadReceiveGondolas(planogramId: string) {
    if (!planogramId) {
        receiveGondolas.value = [];

        return;
    }

    isLoadingReceiveGondolas.value = true;

    try {
        await receiveGondolasHttp.get(planogramGondolasRoute.url({ planogram: planogramId }));
        receiveGondolas.value = receiveGondolasHttp.response?.data ?? [];
    } catch (error) {
        console.error('Erro ao carregar gôndolas:', error);
        toast.error(t('plannerate.sidebar.transfer_section.errors.load_gondolas'));
        receiveGondolas.value = [];
    } finally {
        isLoadingReceiveGondolas.value = false;
    }
}

/**
 * Carrega módulos da gôndola selecionada (modo Trazer)
 */
async function loadSections(gondolaId: string) {
    if (!gondolaId) {
        availableSections.value = [];

        return;
    }

    isLoadingSections.value = true;

    try {
        await receiveSectionsHttp.get(gondolaSectionsRoute.url({ gondola: gondolaId }));
        availableSections.value = receiveSectionsHttp.response?.data ?? [];
    } catch (error) {
        console.error('Erro ao carregar módulos:', error);
        toast.error(t('plannerate.sidebar.transfer_section.errors.load_sections'));
        availableSections.value = [];
    } finally {
        isLoadingSections.value = false;
    }
}

/**
 * Move o módulo para a gôndola selecionada (modo Enviar)
 */
function handleTransfer() {
    // Determina qual módulo usar: pré-selecionado ou selecionado no select
    const sectionIdToTransfer = props.section?.id || selectedSectionToSend.value;

    if (!sectionIdToTransfer || !selectedGondolaId.value) {
        return;
    }

    if (isSameGondola.value) {
        toast.warning(t('plannerate.sidebar.transfer_section.warning_already_in_gondola'));

        return;
    }

    isTransferring.value = true;

    const historyEntry = buildSendHistoryEntry(sectionIdToTransfer);

    router.post(
        transfer.url({ section: sectionIdToTransfer }),
        {
            gondola_id: selectedGondolaId.value,
        },
        {
            preserveScroll: true,
            preserveState: false, // Força reload dos dados
            onSuccess: async () => {
                toast.success(t('plannerate.sidebar.transfer_section.success.moved'));

                operationHistory.value.unshift(historyEntry);
                saveHistoryToStorage();

                // Mantém o dialog aberto — reseta apenas os campos
                selectedGondolaId.value = '';
                selectedSectionToSend.value = '';

                if (currentGondola.value) {
                    selectedPlanogramId.value = currentGondola.value.planogram_id || '';
                    selectedGondolaId.value = currentGondola.value.id;

                    // Recarrega módulos se não há módulo pré-selecionado
                    if (!props.section) {
                        await loadCurrentGondolaSections(currentGondola.value.id);
                    }
                }
            },
            onError: (errors) => {
                console.error('Erro ao mover módulo:', errors);
                const errorMessage = typeof errors === 'string'
                    ? errors
                    : errors?.error || t('plannerate.sidebar.transfer_section.errors.move_section');
                toast.error(errorMessage);
            },
            onFinish: () => {
                isTransferring.value = false;
            },
        },
    );
}

/**
 * Monta a entrada de histórico para uma movimentação de envio.
 */
function buildSendHistoryEntry(sectionId: string): OperationHistory {
    const sectionName = props.section?.name
        || currentGondolaSections.value.find(s => s.id === sectionId)?.name
        || t('plannerate.sidebar.transfer_section.unnamed_section');

    const fromGondola = {
        id: currentGondola.value?.id || '',
        name: currentGondola.value?.name || t('plannerate.sidebar.transfer_section.current_gondola'),
    };
    const toGondola = gondolas.value.find(g => g.id === selectedGondolaId.value)
        || { id: selectedGondolaId.value, name: t('plannerate.sidebar.transfer_section.destination_gondola') };

    return {
        id: `op_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`,
        type: 'send',
        sectionId,
        sectionName,
        fromGondolaId: fromGondola.id,
        fromGondolaName: fromGondola.name,
        toGondolaId: selectedGondolaId.value,
        toGondolaName: toGondola.name,
        timestamp: Date.now(),
        description: t('plannerate.sidebar.transfer_section.sent_description', { from: fromGondola.name, to: toGondola.name }),
    };
}

/**
 * Traz o módulo selecionado para a gôndola atual (modo Trazer)
 */
function handleReceive() {
    if (!selectedSectionId.value || !currentGondola.value?.id) {
        return;
    }

    if (isCurrentGondola.value) {
        toast.warning(t('plannerate.sidebar.transfer_section.warning_already_in_gondola'));

        return;
    }

    isTransferring.value = true;

    const historyEntry = buildReceiveHistoryEntry(selectedSectionId.value);

    router.post(
        transfer.url({ section: selectedSectionId.value }),
        {
            gondola_id: currentGondola.value.id,
        },
        {
            preserveScroll: true,
            preserveState: false, // Força reload dos dados
            onSuccess: () => {
                toast.success(t('plannerate.sidebar.transfer_section.success.received'));

                operationHistory.value.unshift(historyEntry);
                saveHistoryToStorage();

                // Mantém o dialog aberto — reseta apenas os campos
                receiveGondolaId.value = '';
                selectedSectionId.value = '';
                availableSections.value = [];
            },
            onError: (errors) => {
                console.error('Erro ao trazer módulo:', errors);
                const errorMessage = typeof errors === 'string'
                    ? errors
                    : errors?.error || t('plannerate.sidebar.transfer_section.errors.receive_section');
                toast.error(errorMessage);
            },
            onFinish: () => {
                isTransferring.value = false;
            },
        },
    );
}

/**
 * Monta a entrada de histórico para uma movimentação de recebimento.
 */
function buildReceiveHistoryEntry(sectionId: string): OperationHistory {
    const fromGondola = receiveGondolas.value.find(g => g.id === receiveGondolaId.value)
        || { id: receiveGondolaId.value, name: t('plannerate.sidebar.transfer_section.origin_gondola') };
    const toGondola = {
        id: currentGondola.value?.id || '',
        name: currentGondola.value?.name || t('plannerate.sidebar.transfer_section.current_gondola'),
    };
    const selectedSection = availableSections.value.find(s => s.id === sectionId);

    return {
        id: `op_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`,
        type: 'receive',
        sectionId,
        sectionName: selectedSection?.name || t('plannerate.sidebar.transfer_section.unnamed_section'),
        fromGondolaId: receiveGondolaId.value,
        fromGondolaName: fromGondola.name,
        toGondolaId: toGondola.id,
        toGondolaName: toGondola.name,
        timestamp: Date.now(),
        description: t('plannerate.sidebar.transfer_section.received_description', { from: fromGondola.name, to: toGondola.name }),
    };
}

/**
 * Reverte uma operação do histórico (recoloca o módulo na gôndola de origem)
 */
function revertOperation(operation: OperationHistory) {
    if (isReverting.value || revertingOperationId.value) {
        return;
    }

    revertingOperationId.value = operation.id;
    isReverting.value = true;

    router.post(
        transfer.url({ section: operation.sectionId }),
        {
            gondola_id: operation.fromGondolaId,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                toast.success(t('plannerate.sidebar.transfer_section.success.reverted'));
                // Remove do histórico
                const index = operationHistory.value.findIndex(op => op.id === operation.id);

                if (index !== -1) {
                    operationHistory.value.splice(index, 1);
                    saveHistoryToStorage();
                }
            },
            onError: (errors) => {
                console.error('Erro ao reverter operação:', errors);
                const errorMessage = typeof errors === 'string'
                    ? errors
                    : errors?.error || t('plannerate.sidebar.transfer_section.errors.move_section');
                toast.error(errorMessage);
            },
            onFinish: () => {
                isReverting.value = false;
                revertingOperationId.value = null;
            },
        },
    );
}

/**
 * Confirma a limpeza do histórico (acionado pelo AlertDialog)
 */
function confirmClearHistory() {
    operationHistory.value = [];

    try {
        if (isBrowser) {
            window.localStorage.removeItem(STORAGE_KEY);
        }
    } catch (error) {
        console.warn('Erro ao remover histórico do localStorage:', error);
    }

    clearHistoryDialogOpen.value = false;
    toast.success(t('plannerate.sidebar.transfer_section.success.history_cleared'));
}

/**
 * Formata timestamp para exibição
 */
function formatTime(timestamp: number): string {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 10) {
        return t('plannerate.sidebar.transfer_section.time.now');
    } else if (seconds < 60) {
        return t('plannerate.sidebar.transfer_section.time.seconds_ago', { count: seconds.toString() });
    } else if (minutes < 60) {
        return t('plannerate.sidebar.transfer_section.time.minutes_ago', { count: minutes.toString() });
    } else if (hours < 24) {
        return t('plannerate.sidebar.transfer_section.time.hours_ago', { count: hours.toString() });
    } else if (days === 1) {
        return t('plannerate.sidebar.transfer_section.time.yesterday');
    } else if (days < 7) {
        return t('plannerate.sidebar.transfer_section.time.days_ago', { count: days.toString() });
    } else {
        return date.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }
}

/**
 * Fecha o dialog
 */
function handleClose() {
    emit('update:open', false);
    // Reseta todas as seleções
    operationMode.value = 'send';
    selectedPlanogramId.value = '';
    selectedGondolaId.value = '';
    selectedSectionToSend.value = '';
    currentGondolaSections.value = [];
    gondolas.value = [];
    receivePlanogramId.value = '';
    receiveGondolaId.value = '';
    receiveGondolas.value = [];
    selectedSectionId.value = '';
    availableSections.value = [];
    // Histórico permanece salvo no localStorage e será carregado na próxima abertura
}
</script>
