<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Transferir Seção</DialogTitle>
                <DialogDescription>
                    Envie ou traga seções entre gôndolas
                </DialogDescription>
            </DialogHeader>

            <Tabs v-model="operationMode" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="send">
                        <ArrowRight class="mr-2 h-4 w-4" />
                        Enviar
                    </TabsTrigger>
                    <TabsTrigger value="receive">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Trazer
                    </TabsTrigger>
                </TabsList>

                <!-- Modo: Enviar Seção -->
                <TabsContent value="send" class="space-y-4 py-4">
                    <div class="rounded-lg border bg-muted/50 p-3">
                        <p class="text-sm font-medium">
                            {{ section ? `Enviar seção "${section.name}"` : 'Enviar seção' }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ section ? 'Mova esta seção para outra gôndola ou planograma' : 'Selecione uma seção para enviar' }}
                        </p>
                    </div>

                    <!-- Select de Seção (quando não há seção pré-selecionada) -->
                    <div v-if="!section" class="space-y-2">
                        <Label for="send-section-select">Seção</Label>
                        <select
                            id="send-section-select"
                            v-model="selectedSectionToSend"
                            :disabled="isLoadingCurrentSections"
                            class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Selecione a seção</option>
                            <option
                                v-for="sectionItem in currentGondolaSections"
                                :key="sectionItem.id"
                                :value="sectionItem.id"
                            >
                                {{ sectionItem.name }}
                            </option>
                        </select>
                        <p v-if="isLoadingCurrentSections" class="text-sm text-muted-foreground">
                            Carregando seções...
                        </p>
                    </div>

                    <!-- Select de Planograma -->
                    <div class="space-y-2">
                        <Label for="send-planogram-select">Planograma</Label>
                        <select
                            id="send-planogram-select"
                            v-model="selectedPlanogramId"
                            @change="handlePlanogramChange"
                            class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Selecione o planograma</option>
                            <option
                                v-for="planogram in planograms"
                                :key="planogram.id"
                                :value="planogram.id"
                            >
                                {{ planogram.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Select de Gôndola -->
                    <div class="space-y-2">
                        <Label for="send-gondola-select">Gôndola</Label>
                        <select
                            id="send-gondola-select"
                            v-model="selectedGondolaId"
                            :disabled="!selectedPlanogramId || isLoadingGondolas"
                            class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Selecione a gôndola</option>
                            <option
                                v-for="gondola in gondolas"
                                :key="gondola.id"
                                :value="gondola.id"
                            >
                                {{ gondola.name }}
                            </option>
                        </select>
                        <p v-if="isLoadingGondolas" class="text-sm text-muted-foreground">
                            Carregando gôndolas...
                        </p>
                    </div>

                    <!-- Informações adicionais -->
                    <div v-if="selectedGondolaId && !isSameGondola" class="rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                        <p class="text-sm text-blue-900 dark:text-blue-100">
                            <strong>ℹ️</strong> A seção será movida para o final da gôndola selecionada
                        </p>
                    </div>
                    <div v-else-if="isSameGondola" class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                        <p class="text-sm text-amber-900 dark:text-amber-100">
                            <strong>⚠️</strong> A seção já está nesta gôndola
                        </p>
                    </div>

                    <Button 
                        @click="handleTransfer" 
                        :disabled="!selectedGondolaId || isTransferring || isSameGondola || (!section && !selectedSectionToSend)"
                        class="w-full"
                    >
                        <Loader2 v-if="isTransferring" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isSameGondola ? 'Mesma Gôndola' : 'Mover Seção' }}
                    </Button>
                </TabsContent>

                <!-- Modo: Trazer Seção -->
                <TabsContent value="receive" class="space-y-4 py-4">
                    <div class="rounded-lg border bg-muted/50 p-3">
                        <p class="text-sm font-medium">Trazer seção de outra gôndola</p>
                        <p class="text-xs text-muted-foreground mt-1">
                            Selecione uma seção de outra gôndola para trazer para a gôndola atual
                        </p>
                    </div>

                    <!-- Select de Planograma -->
                    <div class="space-y-2">
                        <Label for="receive-planogram-select">Planograma</Label>
                        <select
                            id="receive-planogram-select"
                            v-model="receivePlanogramId"
                            @change="handleReceivePlanogramChange"
                            class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Selecione o planograma</option>
                            <option
                                v-for="planogram in planograms"
                                :key="planogram.id"
                                :value="planogram.id"
                            >
                                {{ planogram.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Select de Gôndola -->
                    <div class="space-y-2">
                        <Label for="receive-gondola-select">Gôndola</Label>
                        <select
                            id="receive-gondola-select"
                            v-model="receiveGondolaId"
                            @change="handleReceiveGondolaChange"
                            :disabled="!receivePlanogramId || isLoadingReceiveGondolas"
                            class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Selecione a gôndola</option>
                            <option
                                v-for="gondola in receiveGondolas"
                                :key="gondola.id"
                                :value="gondola.id"
                            >
                                {{ gondola.name }}
                            </option>
                        </select>
                        <p v-if="isLoadingReceiveGondolas" class="text-sm text-muted-foreground">
                            Carregando gôndolas...
                        </p>
                    </div>

                    <!-- Select de Seção -->
                    <div class="space-y-2">
                        <Label for="receive-section-select">Seção</Label>
                        <select
                            id="receive-section-select"
                            v-model="selectedSectionId"
                            :disabled="!receiveGondolaId || isLoadingSections"
                            class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Selecione a seção</option>
                            <option
                                v-for="sectionItem in availableSections"
                                :key="sectionItem.id"
                                :value="sectionItem.id"
                            >
                                {{ sectionItem.name }}
                            </option>
                        </select>
                        <p v-if="isLoadingSections" class="text-sm text-muted-foreground">
                            Carregando seções...
                        </p>
                    </div>

                    <!-- Informações adicionais -->
                    <div v-if="selectedSectionId && !isCurrentGondola" class="rounded-lg border border-green-200 bg-green-50/50 p-3 dark:border-green-800 dark:bg-green-950/20">
                        <p class="text-sm text-green-900 dark:text-green-100">
                            <strong>ℹ️</strong> A seção será trazida para o final da gôndola atual
                        </p>
                    </div>
                    <div v-else-if="isCurrentGondola" class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                        <p class="text-sm text-amber-900 dark:text-amber-100">
                            <strong>⚠️</strong> A seção já está nesta gôndola
                        </p>
                    </div>

                    <Button 
                        @click="handleReceive" 
                        :disabled="!selectedSectionId || isTransferring || isCurrentGondola"
                        class="w-full"
                    >
                        <Loader2 v-if="isTransferring" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isCurrentGondola ? 'Mesma Gôndola' : 'Trazer Seção' }}
                    </Button>
                </TabsContent>
            </Tabs>

            <!-- Histórico de Operações -->
            <div class="mt-4">
                <Separator class="mb-4" />
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <History class="h-4 w-4 text-muted-foreground" />
                        <h3 class="text-sm font-medium">Histórico de Operações</h3>
                        <Badge v-if="operationHistory.length > 0" variant="secondary" class="ml-1">
                            {{ operationHistory.length }}
                        </Badge>
                    </div>
                    <Button
                        v-if="operationHistory.length > 0"
                        variant="ghost"
                        size="sm"
                        @click="handleClearHistory"
                        :disabled="isReverting"
                        class="h-7 text-xs text-muted-foreground hover:text-destructive"
                    >
                        <Trash2 class="mr-1 h-3 w-3" />
                        Limpar
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
                            Reverter
                        </Button>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed bg-muted/20 p-6 text-center">
                    <History class="mx-auto h-8 w-8 text-muted-foreground/50" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        Nenhuma operação realizada ainda
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground/70">
                        As operações de transferência aparecerão aqui
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleClose">
                    Fechar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, ArrowRight, Clock, History, Loader2, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
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
    originalOrdering: number;
    timestamp: number;
    description: string;
}

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

const editor = usePlanogramEditor();
// const _page = usePage();

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
const selectedSectionToSend = ref<string>(''); // Seção selecionada quando não há seção pré-selecionada
const currentGondolaSections = ref<any[]>([]); // Seções da gôndola atual
const isLoadingCurrentSections = ref(false);
const planograms = ref<any[]>([]);
const gondolas = ref<any[]>([]);
const isLoadingPlanograms = ref(false);
const isLoadingGondolas = ref(false);

// Estado para modo "Trazer"
const receivePlanogramId = ref<string>('');
const receiveGondolaId = ref<string>('');
const receiveGondolas = ref<any[]>([]);
const selectedSectionId = ref<string>('');
const availableSections = ref<any[]>([]);
const isLoadingReceiveGondolas = ref(false);
const isLoadingSections = ref(false);

// Estado compartilhado
const isTransferring = ref(false);
const isReverting = ref(false);
const revertingOperationId = ref<string | null>(null);

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

// Inicializa quando abre o dialog
watch(() => props.open, async (isOpen) => {
    if (isOpen) {
        // Recarrega histórico do localStorage (caso tenha sido atualizado em outra aba)
        const storedHistory = loadHistoryFromStorage();
        operationHistory.value = storedHistory;
        
        // Carrega lista de planogramas
        await loadPlanograms();
        
        // Define planograma e gôndola atuais como padrão (modo Enviar)
        if (currentGondola.value) {
            selectedPlanogramId.value = currentGondola.value.planogram_id || '';
            selectedGondolaId.value = currentGondola.value.id;
            
            // Carrega gondolas do planograma atual
            await loadGondolas(currentGondola.value.planogram_id || '');
            
            // Se não há seção pré-selecionada, carrega seções da gôndola atual
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
        
        // Reseta seção selecionada para enviar
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
    isLoadingPlanograms.value = true;

    try {
        const response = await axios.get('/api/editor/planograms');
        planograms.value = response.data.data;
    } catch (error) {
        console.error('Erro ao carregar planogramas:', error);
        toast.error('Erro ao carregar planogramas');
    } finally {
        isLoadingPlanograms.value = false;
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
        const response = await axios.get(`/api/editor/planograms/${planogramId}/gondolas`);
        gondolas.value = response.data.data;
    } catch (error) {
        console.error('Erro ao carregar gôndolas:', error);
        toast.error('Erro ao carregar gôndolas do planograma');
    } finally {
        isLoadingGondolas.value = false;
    }
}

/**
 * Carrega seções da gôndola atual (para seleção quando não há seção pré-selecionada)
 */
async function loadCurrentGondolaSections(gondolaId: string) {
    if (!gondolaId) {
        currentGondolaSections.value = [];

        return;
    }

    isLoadingCurrentSections.value = true;

    try {
        const response = await axios.get(`/api/editor/gondolas/${gondolaId}/sections`);
        currentGondolaSections.value = response.data.data || [];
    } catch (error) {
        console.error('Erro ao carregar seções da gôndola:', error);
        toast.error('Erro ao carregar seções da gôndola');
    } finally {
        isLoadingCurrentSections.value = false;
    }
}

/**
 * Handler para mudança de planograma
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
        const response = await axios.get(`/api/editor/planograms/${planogramId}/gondolas`);
        receiveGondolas.value = response.data.data;
    } catch (error) {
        console.error('Erro ao carregar gôndolas:', error);
        toast.error('Erro ao carregar gôndolas do planograma');
    } finally {
        isLoadingReceiveGondolas.value = false;
    }
}

/**
 * Carrega seções da gôndola selecionada (modo Trazer)
 */
async function loadSections(gondolaId: string) {
    if (!gondolaId) {
        availableSections.value = [];

        return;
    }

    isLoadingSections.value = true;

    try {
        const response = await axios.get(`/api/editor/gondolas/${gondolaId}/sections`);
        availableSections.value = response.data.data || [];
    } catch (error) {
        console.error('Erro ao carregar seções:', error);
        toast.error('Erro ao carregar seções da gôndola');
    } finally {
        isLoadingSections.value = false;
    }
}

/**
 * Transfere a seção para a gôndola selecionada (modo Enviar)
 */
async function handleTransfer() {
    // Determina qual seção usar: pré-selecionada ou selecionada no select
    const sectionIdToTransfer = props.section?.id || selectedSectionToSend.value;
    
    if (!sectionIdToTransfer || !selectedGondolaId.value) {
        return;
    }

    if (isSameGondola.value) {
        toast.warning('A seção já está nesta gôndola');

        return;
    }

    isTransferring.value = true;

    try {
        // Busca informações da seção antes de transferir (para obter ordering)
        let sectionData: any = null;
        let sectionName = '';
        
        try {
            const sectionInfo = await axios.get(`/api/editor/sections/${sectionIdToTransfer}`);
            sectionData = sectionInfo.data.data || sectionInfo.data;
            sectionName = sectionData?.name || props.section?.name || 'Seção sem nome';
        } catch (error) {
            console.warn('Não foi possível buscar informações da seção:', error);
            sectionName = props.section?.name || 'Seção sem nome';
        }
        
        // Busca informações das gôndolas para o histórico
        const fromGondola = gondolas.value.find(g => g.id === currentGondola.value?.id) || 
                           { id: currentGondola.value?.id || '', name: 'Gôndola atual' };
        const toGondola = gondolas.value.find(g => g.id === selectedGondolaId.value) || 
                         { id: selectedGondolaId.value, name: 'Gôndola destino' };

        // Salva informações para reverter
        const historyEntry: OperationHistory = {
            id: `op_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            type: 'send',
            sectionId: sectionIdToTransfer,
            sectionName: sectionName,
            fromGondolaId: currentGondola.value?.id || '',
            fromGondolaName: fromGondola.name,
            toGondolaId: selectedGondolaId.value,
            toGondolaName: toGondola.name,
            originalOrdering: sectionData?.ordering || props.section?.ordering || 0,
            timestamp: Date.now(),
            description: `Enviada de "${fromGondola.name}" para "${toGondola.name}"`,
        };

        // Faz a requisição para mover a seção
        router.post(
            `/api/editor/sections/${sectionIdToTransfer}/transfer`,
            {
                gondola_id: selectedGondolaId.value,
            },
            {
                preserveScroll: true,
                preserveState: false, // Força reload dos dados
                onSuccess: async () => {
                    toast.success('Seção movida com sucesso!');
                    // Adiciona ao histórico
                    operationHistory.value.unshift(historyEntry);
                    // Salva no localStorage
                    saveHistoryToStorage();
                    // Mantém o dialog aberto
                    // Reseta apenas os campos
                    selectedGondolaId.value = '';
                    selectedSectionToSend.value = '';

                    if (currentGondola.value) {
                        selectedPlanogramId.value = currentGondola.value.planogram_id || '';
                        selectedGondolaId.value = currentGondola.value.id;

                        // Recarrega seções se não há seção pré-selecionada
                        if (!props.section) {
                            await loadCurrentGondolaSections(currentGondola.value.id);
                        }
                    }
                },
                onError: (errors) => {
                    console.error('Erro ao mover seção:', errors);
                    const errorMessage = typeof errors === 'string' 
                        ? errors 
                        : errors?.error || 'Erro ao mover seção';
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    isTransferring.value = false;
                },
            }
        );
    } catch (error) {
        console.error('Erro ao mover seção:', error);
        toast.error('Erro ao mover seção');
        isTransferring.value = false;
    }
}

/**
 * Traz a seção selecionada para a gôndola atual (modo Trazer)
 */
async function handleReceive() {
    if (!selectedSectionId.value || !currentGondola.value?.id) {
        return;
    }

    if (isCurrentGondola.value) {
        toast.warning('A seção já está nesta gôndola');

        return;
    }

    isTransferring.value = true;

    try {
        // Busca informações da seção antes de transferir (para obter ordering)
        let sectionData: any = null;

        try {
            const sectionInfo = await axios.get(`/api/editor/sections/${selectedSectionId.value}`);
            sectionData = sectionInfo.data.data || sectionInfo.data;
        } catch (error) {
            console.warn('Não foi possível buscar informações da seção:', error);
        }
        
        // Busca informações das gôndolas para o histórico
        const fromGondola = receiveGondolas.value.find(g => g.id === receiveGondolaId.value) || 
                           { id: receiveGondolaId.value, name: 'Gôndola origem' };
        const toGondola = { 
            id: currentGondola.value.id, 
            name: currentGondola.value.name || 'Gôndola atual' 
        };

        // Busca a seção selecionada na lista
        const selectedSection = availableSections.value.find(s => s.id === selectedSectionId.value);

        // Salva informações para reverter
        const historyEntry: OperationHistory = {
            id: `op_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            type: 'receive',
            sectionId: selectedSectionId.value,
            sectionName: selectedSection?.name || sectionData?.name || 'Seção sem nome',
            fromGondolaId: receiveGondolaId.value,
            fromGondolaName: fromGondola.name,
            toGondolaId: currentGondola.value.id,
            toGondolaName: toGondola.name,
            originalOrdering: sectionData?.ordering || selectedSection?.ordering || 0,
            timestamp: Date.now(),
            description: `Trazida de "${fromGondola.name}" para "${toGondola.name}"`,
        };

        // Faz a requisição para mover a seção
        router.post(
            `/api/editor/sections/${selectedSectionId.value}/transfer`,
            {
                gondola_id: currentGondola.value.id,
            },
            {
                preserveScroll: true,
                preserveState: false, // Força reload dos dados
                onSuccess: () => {
                    toast.success('Seção trazida com sucesso!');
                    // Adiciona ao histórico
                    operationHistory.value.unshift(historyEntry);
                    // Salva no localStorage
                    saveHistoryToStorage();
                    // Mantém o dialog aberto
                    // Reseta apenas os campos
                    receiveGondolaId.value = '';
                    selectedSectionId.value = '';
                    availableSections.value = [];
                },
                onError: (errors) => {
                    console.error('Erro ao trazer seção:', errors);
                    const errorMessage = typeof errors === 'string' 
                        ? errors 
                        : errors?.error || 'Erro ao trazer seção';
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    isTransferring.value = false;
                },
            }
        );
    } catch (error) {
        console.error('Erro ao trazer seção:', error);
        toast.error('Erro ao trazer seção');
        isTransferring.value = false;
    }
}

/**
 * Reverte uma operação do histórico
 */
async function revertOperation(operation: OperationHistory) {
    if (isReverting.value || revertingOperationId.value) {
return;
}

    revertingOperationId.value = operation.id;
    isReverting.value = true;

    try {
        // Faz a requisição para reverter a transferência
        router.post(
            `/api/editor/sections/${operation.sectionId}/transfer`,
            {
                gondola_id: operation.fromGondolaId,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success('Operação revertida com sucesso!');
                    // Remove do histórico
                    const index = operationHistory.value.findIndex(op => op.id === operation.id);

                    if (index !== -1) {
                        operationHistory.value.splice(index, 1);
                        // Salva no localStorage
                        saveHistoryToStorage();
                    }
                },
                onError: (errors) => {
                    console.error('Erro ao reverter operação:', errors);
                    const errorMessage = typeof errors === 'string' 
                        ? errors 
                        : errors?.error || 'Erro ao reverter operação';
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    isReverting.value = false;
                    revertingOperationId.value = null;
                },
            }
        );
    } catch (error: any) {
        console.error('Erro ao reverter operação:', error);
        const errorMessage = error?.response?.data?.error || error?.message || 'Erro ao reverter operação';
        toast.error(errorMessage);
        isReverting.value = false;
        revertingOperationId.value = null;
    }
}

/**
 * Limpa o histórico de operações (com confirmação)
 */
function handleClearHistory() {
    if (operationHistory.value.length === 0) {
return;
}

    // Confirmação simples via toast
    if (confirm('Tem certeza que deseja limpar todo o histórico de operações?')) {
        operationHistory.value = [];

        // Remove do localStorage
        try {
            if (isBrowser) {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (error) {
            console.warn('Erro ao remover histórico do localStorage:', error);
        }

        toast.success('Histórico limpo com sucesso');
    }
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
        return 'Agora mesmo';
    } else if (seconds < 60) {
        return `Há ${seconds} segundo${seconds > 1 ? 's' : ''}`;
    } else if (minutes < 60) {
        return `Há ${minutes} minuto${minutes > 1 ? 's' : ''}`;
    } else if (hours < 24) {
        return `Há ${hours} hora${hours > 1 ? 's' : ''}`;
    } else if (days === 1) {
        return 'Ontem';
    } else if (days < 7) {
        return `Há ${days} dia${days > 1 ? 's' : ''}`;
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
