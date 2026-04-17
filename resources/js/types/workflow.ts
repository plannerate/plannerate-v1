/**
 * Workflow & Kanban Types
 * Tipos centralizados para o sistema de workflow e Kanban
 */

import type {
    DetailModalConfig,
    FlowKanbanBoardRawData,
    FlowKanbanCardConfig,
    FlowKanbanGroupConfig,
} from '@flow'

/**
 * Etapa do workflow (coluna do Kanban)
 */
export interface WorkflowStep {
    id: string
    name: string
    description: string | null
    order: number
    color: string | null
    templateNextStep?: {
        id: string
        name: string
    }
    templatePreviousStep?: {
        id: string
        name: string
    }
}

/**
 * Informações da góndola
 */
/**
 * Board do Kanban (completo)
 */
export type KanbanBoard = FlowKanbanBoardRawData

/**
 * Filtros do Kanban — valores ativos
 */
export interface KanbanFilters {
    planogram_id: string | null
    assigned_to: string | null
    user_id: string | null
    status: string | null
    only_overdue: boolean
    show_completed: boolean
    loja_id: string | null
}

/**
 * Opção normalizada pelo backend (SelectFilter::toArray() + normalizeOptions())
 */
export interface FilterOption {
    value: string
    label: string
}

/**
 * Configuração de um filtro do tipo select vinda do backend (SelectFilter::toArray())
 */
export interface KanbanFilterConfig {
    id: string
    name: string
    label: string
    /** Tipo do filtro (ex: 'select', 'text'). Opcional quando 'component' já está definido. */
    type?: string
    component: string        // ex: 'filter-select'
    options: FilterOption[]
    placeholder?: string
    visible?: boolean
    classes?: string
    class?: string
}

/**
 * Estrutura completa de filtros retornada pelo backend
 */
export interface KanbanFiltersState {
    /** Configurações dos filtros (labels, opções, componente).
     *  Inertia::defer() → null no primeiro render, array após o segundo request. */
    data: KanbanFilterConfig[] | null
    /** Valores ativos no momento da requisição */
    values: KanbanFilters
}

/**
 * Config de grupo para validação de drop no KanbanBoard.
 * Mapeia um grupo (ex: planograma) aos step IDs permitidos pelo seu workflow.
 * Espelha FlowKanbanGroupConfig do pacote laravel-raptor-flow.
 */
export type FlowGroupConfig = FlowKanbanGroupConfig

/**
 * Props da página Index do Kanban
 */
export interface KanbanIndexProps {
    planogramIdForCreate: string | null
    board: KanbanBoard
    /** Configs de grupo para validar drops entre colunas do KanbanBoard */
    groupConfigs: FlowGroupConfig[]
    /** Filtros: configurações (opções de UI) + valores ativos */
    filters: KanbanFiltersState
    /** Roles do usuário autenticado (para permissões de UI) */
    userRoles?: string[]
    /** Configuração declarativa do card do Kanban */
    cardConfig?: FlowKanbanCardConfig | null
    /** Configuração do modal de detalhes — sections e actions geradas pelo backend */
    detailModalConfig?: DetailModalConfig | null
}
