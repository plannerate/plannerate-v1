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

        return JSON.parse(JSON.stringify(state));
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

        // Salva no localStorage após registrar
        saveToLocalStorage();
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

        // Salva no localStorage após undo
        saveToLocalStorage();

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

        // Salva no localStorage após redo
        saveToLocalStorage();

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
        
        // LocalStorage
        saveToLocalStorage,
        loadFromLocalStorage,
        clearLocalStorage,
    };
}
