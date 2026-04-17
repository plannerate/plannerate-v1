export type StatusBadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

const planogramStatusLabels: Record<string, string> = {
    draft: 'Rascunho',
    published: 'Publicado',
    archived: 'Arquivado',
};

const clientStatusLabels: Record<string, string> = {
    active: 'Ativo',
    inactive: 'Inativo',
    draft: 'Rascunho',
    published: 'Publicado',
    archived: 'Arquivado',
};

const workflowStatusLabels: Record<string, string> = {
    pending: 'Pendente',
    in_progress: 'Em Andamento',
    completed: 'Concluído',
    blocked: 'Bloqueado',
    skipped: 'Ignorado',
};

const workflowStatusVariants: Record<string, StatusBadgeVariant> = {
    pending: 'secondary',
    in_progress: 'default',
    completed: 'outline',
    blocked: 'destructive',
    skipped: 'secondary',
};

function resolveStatusLabel(labels: Record<string, string>, status?: string | null): string {
    if (!status) {
        return 'Sem status';
    }

    return labels[status] ?? status;
}

export function getPlanogramStatusLabel(status?: string | null): string {
    return resolveStatusLabel(planogramStatusLabels, status);
}

export function getClientStatusLabel(status?: string | null): string {
    return resolveStatusLabel(clientStatusLabels, status);
}

export function getWorkflowStatusLabel(status?: string | null): string {
    return resolveStatusLabel(workflowStatusLabels, status);
}

export function getWorkflowStatusVariant(status?: string | null): StatusBadgeVariant {
    if (!status) {
        return 'secondary';
    }

    return workflowStatusVariants[status] ?? 'secondary';
}
