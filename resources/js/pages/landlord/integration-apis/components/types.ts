export type FieldMapRow = {
    id: string;
    target: string;
    source: string;
    transforms: string[];
};

export type ValidationRow = {
    id: string;
    type: string;
    sources: string;
    allowed_values: string;
};

export type PivotTableRow = {
    id: string;
    table: string;
    local_key: string;
    foreign_key: string;
    related_key: string;
    unique_by: string;
};

export type RequestPathRow = {
    id: string;
    target_table: string;
    fallback_path: string;
    id_prefix: string;
    unique_by: string;
    include_store_in_id: boolean;
    initial_days: string;
    max_page: string;
    min_page_size: string;
    max_page_size: string;
    changed_since: string;
    start: string;
    end: string;
    field_map: FieldMapRow[];
    pivot_tables: PivotTableRow[];
    validations: ValidationRow[];
};

export type FieldMapTableOption = {
    label: string;
    columns: string[];
};
