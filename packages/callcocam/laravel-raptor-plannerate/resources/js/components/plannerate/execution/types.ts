/**
 * Tipos do payload da camada de Execução em Loja, espelhando o que o
 * WorkflowExecutionLayerService::buildPayload envia via Inertia::optional.
 */

export interface ExecutionEvidence {
    id: string;
    type: string | null;
    module_label: string | null;
    product_id: string | null;
    file_url: string | null;
    file_name: string | null;
    notes: string | null;
    created_by: string | null;
    created_at: string | null;
}

export interface ExecutionDivergence {
    id: string;
    type: string | null;
    module_label: string | null;
    shelf_label: string | null;
    position_label: string | null;
    product_id: string | null;
    notes: string | null;
    status: string | null;
    resolution_notes: string | null;
    photo_urls: string[];
    created_by: string | null;
    created_at: string | null;
}

export interface EvidenceBreakdown {
    type: string;
    required: number;
    provided: number;
}

export interface EvidenceSummary {
    required: number;
    provided: number;
    satisfied: boolean;
    breakdown: EvidenceBreakdown[];
}

export interface ExecutionPayload {
    id: string;
    status: string | null;
    responsible: string | null;
    started_by: string | null;
    started_at: string | null;
    sla_date: string | null;
    sla_days_remaining: number | null;
    evidences: ExecutionEvidence[];
    divergences: ExecutionDivergence[];
    evidence_summary: EvidenceSummary;
    pending_divergences_count: number;
    can_complete: boolean;
}
