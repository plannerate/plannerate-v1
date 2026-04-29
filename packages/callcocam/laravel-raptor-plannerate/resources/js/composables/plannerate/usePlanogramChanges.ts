// ============================================================================
// IMPORTS
// ============================================================================

import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { EntityType } from '@/types/planogram';

// ============================================================================
// TYPES & INTERFACES
// ============================================================================

/**
 * Tipos de mudanças suportadas pelo sistema de delta/diff
 * Cada tipo representa uma operação específica no planograma
 */
export type ChangeType =
    | 'shelf_update' // Atualiza propriedades de uma prateleira
    | 'shelf_move' // Move prateleira dentro da mesma seção
    | 'shelf_transfer' // Transfere prateleira entre seções
    | 'section_update' // Atualiza propriedades de uma seção
    | 'product_placement' // Coloca/move produto em uma camada
    | 'product_update' // Atualiza propriedades de um produto
    | 'product_removal' // Remove produto de uma camada
    | 'layer_update' // Atualiza propriedades de uma camada
    | 'layer_create' // Cria nova camada
    | 'segment_update' // Atualiza propriedades de um segmento
    | 'gondola_update' // Atualiza propriedades gerais da gôndola
    | 'gondola_scale' // Altera escala/zoom da gôndola
    | 'gondola_alignment' // Altera alinhamento da gôndola
    | 'gondola_flow'; // Altera fluxo de leitura da gôndola

/**
 * Representa uma mudança/delta individual
 * Mudanças são mescladas automaticamente se referem à mesma entidade
 */
export interface Change {
    type: ChangeType;
    entityType: EntityType;
    entityId: string;
    data: Record<string, any>;
    timestamp: number;
}

/**
 * Payload enviado ao backend para salvar mudanças
 * Contém todas as mudanças pendentes + metadata
 */
export interface SavePayload {
    gondola_id: string;
    changes: Change[];
    metadata: {
        total_changes: number;
        last_modified: number;
    };
}

/**
 * Opções para gravação de mudanças
 */
export interface RecordChangeOptions {
    schedule?: boolean; // Se deve agendar auto-save (default: true)
    onSaved?: () => void | Promise<void>; // Callback executado após save com sucesso
}

/**
 * Opções para salvamento de mudanças
 */
export interface SaveChangesOptions {
    isAuto?: boolean; // Se é auto-save ou manual
    onReconcile?: (entities?: any) => void; // Callback para reconciliação com backend
}

// ============================================================================
// ESTADO GLOBAL (SINGLETON PATTERN)
// ============================================================================

/**
 * Map de mudanças pendentes indexadas por chave única (entityType_entityId)
 * Usar Map permite mesclagem automática de mudanças na mesma entidade
 */
const pendingChanges = ref<Map<string, Change>>(new Map());

/**
 * Map de callbacks para executar após save bem-sucedido
 * Chave é a mesma das mudanças (entityType_entityId)
 */
const pendingCallbacks = new Map<string, (() => void | Promise<void>)[]>();

/**
 * Indica se está salvando no momento
 */
const isSaving = ref(false);

/**
 * Timestamp do último salvamento bem-sucedido
 */
const lastSavedAt = ref<number | null>(null);

/**
 * Timer para auto-save com debounce (3 segundos)
 */
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * Número máximo de mudanças pendentes antes de forçar salvamento imediato
 * Evita acumular muitas alterações na memória
 */
const MAX_PENDING_CHANGES_BEFORE_SAVE = 10;

/**
 * Rota do backend para auto-save
 */
const autoSaveRoute = ref<string | null>(null);

/**
 * ID da gôndola para auto-save
 */
const autoSaveGondolaId = ref<string | null>(null);

/**
 * Indica se auto-save está habilitado
 * Default: true (ou valor do localStorage)
 */
const isBrowser = typeof window !== 'undefined';
const AUTO_SAVE_STORAGE_KEY = 'planogram-auto-save-enabled';

const autoSaveEnabled = ref(
    isBrowser
        ? window.localStorage.getItem(AUTO_SAVE_STORAGE_KEY) !== 'false'
        : true,
);

// ============================================================================
// COMPOSABLE
// ============================================================================

export function usePlanogramChanges() {
    // ========================================================================
    // CONFIGURAÇÃO
    // ========================================================================

    /**
     * Define o contexto para auto-save
     * Deve ser chamado uma vez ao inicializar o editor
     *
     * @param gondolaId - ID da gôndola sendo editada
     * @param route - Rota do backend para salvar mudanças
     *
     * @example
     * const changes = usePlanogramChanges();
     * changes.setAutoSaveContext(gondola.id, '/api/gondolas/save-changes');
     */
    function setAutoSaveContext(gondolaId: string, route: string) {
        autoSaveGondolaId.value = gondolaId;
        autoSaveRoute.value = route;
    }

    // ========================================================================
    // REGISTRO DE MUDANÇAS
    // ========================================================================

    /**
     * Registra uma mudança no sistema de deltas
     *
     * - Se já existe uma mudança para a mesma entidade, mescla os dados
     * - Reinicia o timer de auto-save (debounce de 3 segundos)
     * - Mudanças são armazenadas em memória até serem salvas
     *
     * @param change - Mudança a ser registrada (timestamp é adicionado automaticamente)
     * @param options - Opções de agendamento
     *
     * @example
     * recordChange({
     *   type: 'shelf_move',
     *   entityType: 'shelf',
     *   entityId: shelf.id,
     *   data: { section_id: newSection.id, shelf_position: 150 }
     * });
     */
    function recordChange(
        change: Omit<Change, 'timestamp'>,
        options: RecordChangeOptions = { schedule: true },
    ) {
        // Cria chave única para a entidade
        const key = `${change.entityType}_${change.entityId}`;

        // Verifica se já existe mudança para esta entidade
        const existing = pendingChanges.value.get(key);

        if (existing) {
            // Mescla dados mantendo o histórico
            pendingChanges.value.set(key, {
                ...existing,
                data: { ...existing.data, ...change.data },
                timestamp: Date.now(),
            });
        } else {
            // Cria nova mudança
            pendingChanges.value.set(key, {
                ...change,
                timestamp: Date.now(),
            });
        }

        // Armazena callback se fornecido
        if (options.onSaved) {
            if (!pendingCallbacks.has(key)) {
                pendingCallbacks.set(key, []);
            }

            pendingCallbacks.get(key)!.push(options.onSaved);
        }

        // Verifica se deve salvar imediatamente ou agendar
        if (options.schedule !== false) {
            const changeCount = pendingChanges.value.size;
            
            // Se atingiu o threshold, salva imediatamente
            if (changeCount >= MAX_PENDING_CHANGES_BEFORE_SAVE) {
                scheduleAutoSave(0); // Delay 0 = salva imediatamente
            } else {
                // Caso contrário, usa debounce normal de 3s
                scheduleAutoSave();
            }
        }
    }

    // ========================================================================
    // AUTO-SAVE
    // ========================================================================

    /**
     * Agenda auto-save com debounce
     *
     * - Cancela timer anterior se existir
     * - Aguarda período de inatividade (default: 3s)
     * - Se delay = 0, salva imediatamente (útil quando atinge threshold)
     * - Só executa se houver mudanças pendentes
     *
     * @param delay - Tempo de debounce em ms (default: 3000, 0 = imediato)
     */
    function scheduleAutoSave(delay = 3000) {
        // Não agenda se auto-save estiver desabilitado
        if (!autoSaveEnabled.value) {
            return;
        }

        // Cancela timer anterior
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }

        // Agenda novo salvamento (ou executa imediatamente se delay = 0)
        autoSaveTimer = setTimeout(() => {
            if (
                autoSaveEnabled.value &&
                hasChanges.value &&
                autoSaveRoute.value &&
                autoSaveGondolaId.value &&
                !isSaving.value
            ) {
                void saveChanges(autoSaveGondolaId.value, autoSaveRoute.value, {
                    isAuto: true,
                });
            }
        }, delay);
    }

    /**
     * Cancela o timer de auto-save
     * Útil ao desmontar componente ou navegar para outra página
     */
    function cancelAutoSave() {
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = null;
        }
    }

    // ========================================================================
    // SALVAMENTO
    // ========================================================================

    /**
     * Salva todas as mudanças pendentes no backend
     *
     * - Envia apenas deltas (mudanças), não o estado completo
     * - Backend aplica mudanças transacionalmente
     * - Limpa mudanças pendentes após sucesso
     * - Atualiza timestamp de último salvamento
     *
     * @param gondolaId - ID da gôndola (pode ser diferente do contexto)
     * @param route - Rota do backend (opcional, usa contexto se não fornecido)
     * @param options - Opções de salvamento e reconciliação
     * @returns Promise<boolean> - true se salvou com sucesso
     *
     * @example
     * const success = await changes.saveChanges(gondola.id);
     * if (success) {
     *   console.log('Mudanças salvas!');
     * }
     */
    async function saveChanges(
        gondolaId: string,
        route?: string,
        options: SaveChangesOptions = {},
    ): Promise<boolean> {
        // Valida se há mudanças
        if (!hasChanges.value) {
            return false;
        }

        isSaving.value = true;

        try {
            // Monta payload
            const payload: SavePayload = {
                gondola_id: gondolaId,
                changes: Array.from(pendingChanges.value.values()),
                metadata: {
                    total_changes: pendingChanges.value.size,
                    last_modified: Date.now(),
                },
            };

            // Se não tiver rota, simula salvamento (modo de desenvolvimento/teste)
            if (!route && !autoSaveRoute.value) {
                await new Promise((resolve) => setTimeout(resolve, 500));
                clearChanges();
                lastSavedAt.value = Date.now();
                isSaving.value = false;

                return true;
            }

            // Usa rota fornecida ou contexto
            const targetRoute = route || autoSaveRoute.value;

            if (!targetRoute) {
                throw new Error('Rota de salvamento não configurada');
            }

            // Envia via Inertia
            return new Promise((resolve) => {
                router.post(
                    targetRoute,
                    {
                        gondola_id: payload.gondola_id,
                        changes: payload.changes,
                        metadata: payload.metadata,
                    } as any,
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: async (page) => {
                            // Reconciliação com backend (opcional)
                            const response = page?.props || {};
                            const reconciliationData = {
                                changes_applied: response.changes_applied,
                                gondola_updated_at: response.gondola_updated_at,
                                entities: response.entities,
                            };

                            if (
                                options.onReconcile &&
                                reconciliationData.entities
                            ) {
                                options.onReconcile(
                                    reconciliationData.entities,
                                );
                            }

                            // Executa callbacks pendentes ANTES de limpar
                            const callbacksToExecute = Array.from(pendingCallbacks.values()).flat();
                            
                            // Limpa mudanças e atualiza timestamp
                            clearChanges();
                            lastSavedAt.value = Date.now();
                            
                            // Executa callbacks DEPOIS de limpar (para evitar re-save)
                            for (const callback of callbacksToExecute) {
                                try {
                                    await callback();
                                } catch (error) {
                                    console.error('❌ Erro ao executar callback onSaved:', error);
                                }
                            }
                            
                            resolve(true);
                        },
                        onError: (errors) => {
                            console.error(
                                '❌ Erro ao salvar mudanças:',
                                errors,
                            );
                            resolve(false);
                        },
                        onFinish: () => {
                            isSaving.value = false;
                        },
                    },
                );
            });
        } catch (error) {
            console.error('❌ Erro ao salvar mudanças:', error);
            isSaving.value = false;

            return false;
        }
    }

    /**
     * Limpa todas as mudanças pendentes
     * Também cancela qualquer auto-save agendado
     *
     * ⚠️ Use com cuidado - esta ação não pode ser desfeita
     */
    function clearChanges() {
        pendingChanges.value.clear();
        pendingCallbacks.clear();
        cancelAutoSave();
    }

    // ========================================================================
    // COMPUTED PROPERTIES
    // ========================================================================

    /**
     * Indica se há mudanças pendentes
     */
    const hasChanges = computed(() => pendingChanges.value.size > 0);

    /**
     * Número de mudanças pendentes
     */
    const changeCount = computed(() => pendingChanges.value.size);

    /**
     * Indica se está próximo do threshold de salvamento automático
     * Útil para mostrar feedback visual ao usuário
     */
    const isNearSaveThreshold = computed(() => 
        pendingChanges.value.size >= MAX_PENDING_CHANGES_BEFORE_SAVE * 0.7
    );

    /**
     * Array de todas as mudanças pendentes
     */
    const changes = computed(() => Array.from(pendingChanges.value.values()));

    // ========================================================================
    // RETURN
    // ========================================================================

    /**
     * Habilita ou desabilita auto-save
     * Salva preferência no localStorage
     *
     * @param enabled - true para habilitar, false para desabilitar
     */
    function setAutoSave(enabled: boolean) {
        autoSaveEnabled.value = enabled;

        // Salva no localStorage
        if (isBrowser) {
            window.localStorage.setItem(AUTO_SAVE_STORAGE_KEY, String(enabled));
        }

        if (enabled) {
            // Se há mudanças pendentes, agenda salvamento
            if (hasChanges.value) {
                scheduleAutoSave();
            }
        } else {
            cancelAutoSave();
        }
    }

    /**
     * Alterna auto-save on/off
     */
    function toggleAutoSave() {
        setAutoSave(!autoSaveEnabled.value);
    }

    /**
     * Busca uma mudança pendente por chave
     * Útil para fazer merge correto de dados aninhados
     *
     * @param key - Chave da mudança (formato: entityType_entityId)
     * @returns Change pendente ou undefined
     */
    function getPendingChange(key: string): Change | undefined {
        return pendingChanges.value.get(key);
    }

    return {
        // Estado
        isSaving,
        hasChanges,
        changeCount,
        isNearSaveThreshold,
        changes,
        lastSavedAt,
        autoSaveEnabled,

        // Configuração
        setAutoSaveContext,
        setAutoSave,
        toggleAutoSave,

        // Métodos principais
        recordChange,
        saveChanges,
        clearChanges,
        cancelAutoSave,
        getPendingChange,
    };
}
