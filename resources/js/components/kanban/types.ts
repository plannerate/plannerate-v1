export type AssignedUser = {
    id: string;
    name: string | null;
};

export type KanbanExecutionAction = 'start' | 'pause' | 'resume' | 'complete' | 'abandon';

export type Execution = {
    id: string;
    gondola_id: string;
    gondola_name: string | null;
    gondola_location: string | null;
    planogram_name: string | null;
    step_name: string | null;
    status: 'pending' | 'active' | 'paused' | 'completed' | 'cancelled';
    assigned_to_user: AssignedUser | null;
    started_by: AssignedUser | null;
    started_at: string | null;
    sla_date: string | null;
    can_start: boolean;
    can_pause: boolean;
    can_resume: boolean;
    can_complete: boolean;
    can_abandon: boolean;
    can_move: boolean;
};

export type BoardStep = {
    id: string;
    name: string;
    description: string | null;
    color: string | null;
    icon: string | null;
    suggested_order: number;
    is_required: boolean;
    is_skipped: boolean;
    status: string;
};

export type BoardColumn = {
    step: BoardStep;
    executions: Execution[];
};

export type ExecutionDetails = {
    execution: {
        id: string;
        status: Execution['status'];
        gondola: { id: string; name: string | null; location: string | null } | null;
        step: { id: string; name: string; description: string | null } | null;
        assigned_to_user: AssignedUser | null;
        started_by: AssignedUser | null;
        started_at: string | null;
        sla_date: string | null;
        can_start: boolean;
        can_pause: boolean;
        can_resume: boolean;
        can_complete: boolean;
        can_abandon: boolean;
        can_move: boolean;
    };
    allowed_users: AssignedUser[];
};

export type WorkflowHistory = {
    id: string;
    action: 'started' | 'moved' | 'paused' | 'resumed' | 'assigned' | 'completed' | 'cancelled' | 'restored';
    description: string | null;
    from_step_id: string | null;
    to_step_id: string | null;
    previous_responsible_id: string | null;
    new_responsible_id: string | null;
    can_restore: boolean;
    performed_at: string | null;
    performed_by: AssignedUser | null;
};

export type KanbanPageProps = {
    subdomain: string;
    planograms: Array<{ id: string; name: string; store: string | null; store_id: string | null }>;
    stores: Array<{ id: string; name: string }>;
    users: Array<{ id: string; name: string }>;
    filters: { planogram_id?: string; store_id?: string; gondola_search?: string; status?: string };
    board: BoardColumn[] | null;
    selected_planogram: { id: string; name: string; store: string | null } | null;
    can_initiate: boolean;
};
