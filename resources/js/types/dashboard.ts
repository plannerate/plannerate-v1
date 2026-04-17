export interface ClientWithDomain {
    id: string;
    name: string;
    slug: string;
    status: string;
    domain: {
        domain: string;
        url: string;
        is_primary: boolean;
    } | null;
}

export interface RecentPlanogram {
    id: string;
    name: string;
    status: string;
    client_name?: string;
    store_name?: string;
    created_at: string;
}

export interface PlanogramStats {
    total_planograms: number;
    total_gondolas: number;
    total_products: number;
    status_stats: {
        draft: number;
        published: number;
        archived: number;
    };
    recent_planograms: RecentPlanogram[];
    top_categories: Array<{ name: string; count: number }>;
    top_products_in_gondolas: Array<{ name: string; count: number }>;
    planograms_by_month: Array<{ month: string; count: number; key: string }>;
    card_trends: {
        planograms: number[];
        gondolas: number[];
        products: number[];
        drafts: number[];
    };
}

export interface ChartDataset {
    label: string;
    data: number[];
}

export interface ChartPayload {
    labels: string[];
    datasets: ChartDataset[];
}

export interface ReportChartPayload {
    type: 'bar' | 'doughnut' | 'horizontal-bar';
    label: string;
    data: ChartPayload;
}

export interface WorkflowReport {
    summary: {
        total_executions: number;
        total_metrics: number;
        total_history_events: number;
    };
    filters: {
        values: {
            flow_slug: string | null;
            date_from: string | null;
            date_to: string | null;
            responsible_id: string | null;
        };
        options: {
            flows: Array<{ value: string; label: string }>;
            responsibles: Array<{ value: string; label: string }>;
        };
    };
    charts: {
        status: ReportChartPayload;
        responsible: ReportChartPayload;
        sla: ReportChartPayload;
        step_avg_effective_minutes: ReportChartPayload;
    };
    tables: {
        responsible_activity: {
            label: string;
            data: Array<{ responsible_id: string; name: string; actions_count: number; avg_duration_minutes: number | null }>;
        };
    };
}
