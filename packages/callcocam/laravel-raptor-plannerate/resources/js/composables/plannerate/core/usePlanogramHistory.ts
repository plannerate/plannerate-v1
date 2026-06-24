import { computed, readonly, ref } from 'vue';
import type { ActionType } from '@/types/planogram';

interface HistorySnapshot {
    shelfId?: string;
    sectionId?: string;
    sectionIds?: string[]; // Para operações em múltiplas seções (ex: reordenação)
    segmentId?: string;
    layerId?: string;
    beforeState: any; // Estado completo ANTES da ação
    afterState: any; // Estado completo DEPOIS da ação
    type: ActionType;
    description: string;
    timestamp: number;
}

// Estado global do histórico
const historyStack = ref<HistorySnapshot[]>([]);
const currentIndex = ref(-1);
const maxHistory = 20; // Reduzido de 50 para 20 para ser mais seguro
const localStorageKey = 'plannerate_history';
const localStorageMaxSize = 15; // Máximo de 15 itens no localStorage (mais seguro)
const isBrowser = typeof window !== 'undefined';

/**
 * Timer (módulo-global) do debounce de persistência no localStorage. Global para
 * coalescer chamadas de TODAS as instâncias do composable — a persistência é cara
 * (JSON.stringify de até 15 snapshots) e era feita sincronamente a cada ação,
 * adicionando latência no mouseup/drop. Ver `scheduleSaveToLocalStorage`.
 */
let saveDebounceTimer: ReturnType<typeof setTimeout> | null = null;
const SAVE_DEBOUNCE_MS = 500;

export function usePlanogramHistory() {
    // Flags computadas baseadas no estado global
    const canUndo = computed(() => currentIndex.value >= 0);
    const canRedo = computed(
        () => currentIndex.value < historyStack.value.length - 1,
    );

    /**
     * Salva histórico no localStorage
     * Limita a 15 itens para evitar exceder quota do localStorage
     */
    function saveToLocalStorage() {
        if (!isBrowser) {
            return;
        }

        try {
            // Pega apenas os últimos N itens para não exceder quota
            const itemsToSave = historyStack.value.slice(-localStorageMaxSize);
            
            // Calcula o índice ajustado
            const offset = historyStack.value.length - itemsToSave.length;
            const adjustedIndex = currentIndex.value - offset;
            
            const data = {
                history: itemsToSave,
                currentIndex: adjustedIndex,
                savedAt: Date.now(),
            };
            
            window.localStorage.setItem(localStorageKey, JSON.stringify(data));
          
        } catch {
            // Se exceder quota, limpa e tenta novamente com menos itens
              try {
                const itemsToSave = historyStack.value.slice(-5); // Apenas 5 itens
                const offset = historyStack.value.length - itemsToSave.length;
                const adjustedIndex = currentIndex.value - offset;
                
                const data = {
                    history: itemsToSave,
                    currentIndex: adjustedIndex,
                    savedAt: Date.now(),
                };
                
                window.localStorage.setItem(localStorageKey, JSON.stringify(data));
              } catch {
                console.error('❌ Falha ao salvar histórico mesmo no modo reduzido');
            }
        }
    }

    /**
     * Agenda a persistência no localStorage com debounce (~500ms).
     *
     * Em sequências rápidas de ações (vários drops seguidos) evita pagar um
     * JSON.stringify completo a cada uma — só persiste após a inatividade. O
     * histórico no localStorage é apenas um cache de conveniência (TTL 24h), então
     * a pequena janela em que o disco fica desatualizado é aceitável.
     */
    function scheduleSaveToLocalStorage() {
        if (!isBrowser) {
            return;
        }

        if (saveDebounceTimer) {
            clearTimeout(saveDebounceTimer);
        }

        saveDebounceTimer = setTimeout(() => {
            saveDebounceTimer = null;
            saveToLocalStorage();
        }, SAVE_DEBOUNCE_MS);
    }

    /**
     * Carrega histórico do localStorage
     */
    function loadFromLocalStorage() {
        if (!isBrowser) {
            return false;
        }

        try {
            const saved = window.localStorage.getItem(localStorageKey);

            if (!saved) {
return false;
}
            
            const data = JSON.parse(saved);
            
            // Valida estrutura
            if (!data.history || !Array.isArray(data.history)) {
                console.warn('⚠️ Histórico inválido no localStorage');

                return false;
            }
            
            // Verifica se não é muito antigo (mais de 24h)
            const savedAt = data.savedAt || 0;
            const age = Date.now() - savedAt;
            const maxAge = 24 * 60 * 60 * 1000; // 24 horas
            
            if (age > maxAge) {
                window.localStorage.removeItem(localStorageKey);

                return false;
            }
            
            historyStack.value = data.history;
            currentIndex.value = Math.min(
                data.currentIndex,
                data.history.length - 1,
            );
            
             
            return true;
        } catch (error) {
            console.warn('⚠️ Erro ao carregar histórico do localStorage:', error);
            window.localStorage.removeItem(localStorageKey);

            return false;
        }
    }

    /**
     * Limpa histórico do localStorage
     */
    function clearLocalStorage() {
        if (!isBrowser) {
            return;
        }

        // Cancela qualquer persistência debounced pendente para que não re-grave
        // histórico obsoleto logo após a limpeza.
        if (saveDebounceTimer) {
            clearTimeout(saveDebounceTimer);
            saveDebounceTimer = null;
        }

        try {
            window.localStorage.removeItem(localStorageKey);
        } catch (error) {
            console.warn('⚠️ Erro ao limpar localStorage:', error);
        }
    }

    // Inicializa o histórico
    function initializeHistory() {
        // Tenta carregar do localStorage primeiro
        const loaded = loadFromLocalStorage();
        
        if (!loaded) {
            historyStack.value = [];
            currentIndex.value = -1;
        } 
    }

    /**
     * Cria um deep clone do estado para salvar no histórico
     */
    function cloneState(state: any): any {
        if (!state) {
return null;
}

        // structuredClone é nativo e bem mais rápido que o round-trip JSON para
        // objetos grandes (árvore de snapshot). Faz fallback para JSON quando o
        // estado contém valores não-clonáveis (ex.: funções) — caso em que o JSON
        // simplesmente os descarta, preservando o comportamento anterior.
        try {
            return structuredClone(state);
        } catch {
            return JSON.parse(JSON.stringify(state));
        }
    }

    /**
     * Registra uma ação no histórico com estados before/after
     */
    function recordAction(snapshot: Omit<HistorySnapshot, 'timestamp'>) {
        // Remove ações após o índice atual (se fez undo e depois uma nova ação)
        if (currentIndex.value < historyStack.value.length - 1) {
            historyStack.value = historyStack.value.slice(
                0,
                currentIndex.value + 1,
            );
        }

        // Adiciona nova ação
        const action: HistorySnapshot = {
            ...snapshot,
            timestamp: Date.now(),
        };

        historyStack.value.push(action);

        // Limita tamanho do histórico
        if (historyStack.value.length > maxHistory) {
            historyStack.value.shift();
        } else {
            currentIndex.value++;
        }

        // Persiste no localStorage (debounced) após registrar
        scheduleSaveToLocalStorage();
    }

    /**
     * Desfaz a última ação
     */
    function undoAction(): HistorySnapshot | null {
        if (!canUndo.value) {
            console.warn('⚠️ Não há ações para desfazer');

            return null;
        }

        const action = historyStack.value[currentIndex.value];
        currentIndex.value--;

        // Persiste no localStorage (debounced) após undo
        scheduleSaveToLocalStorage();

        return action;
    }

    /**
     * Refaz a última ação desfeita
     */
    function redoAction(): HistorySnapshot | null {
        if (!canRedo.value) {
            console.warn('⚠️ Não há ações para refazer');

            return null;
        }

        currentIndex.value++;
        const action = historyStack.value[currentIndex.value];

        // Persiste no localStorage (debounced) após redo
        scheduleSaveToLocalStorage();

        return action;
    }

    // Limpa todo o histórico
    function clearHistory() {
        historyStack.value = [];
        currentIndex.value = -1;
        clearLocalStorage();
    }

    // Obtém as últimas N ações
    function getRecentActions(count: number = 10) {
        return historyStack.value.slice(-count);
    }

    /**
     * Mescla dados extras no beforeState da ação mais recente do histórico.
     * Usado para retroativamente vincular contexto (ex: produto rejeitado) a uma
     * ação já registrada, de modo que o undo possa restaurar o estado completo.
     */
    function patchCurrentBeforeState(data: Record<string, any>): void {
        if (currentIndex.value < 0) {
            return;
        }

        const current = historyStack.value[currentIndex.value];

        if (!current) {
            return;
        }

        if (current.beforeState === null || current.beforeState === undefined) {
            current.beforeState = data;
        } else {
            Object.assign(current.beforeState, data);
        }

        scheduleSaveToLocalStorage();
    }

    return {
        // Estado (agora são computed refs)
        canUndo,
        canRedo,
        historyStack: readonly(historyStack),

        // Métodos
        initializeHistory,
        undoAction,
        redoAction,
        recordAction,
        clearHistory,
        getRecentActions,
        cloneState,
        
        // Patch retroativo no beforeState da ação atual
        patchCurrentBeforeState,

        // LocalStorage
        saveToLocalStorage,
        loadFromLocalStorage,
        clearLocalStorage,
    };
}
