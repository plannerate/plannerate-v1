export type FieldMapRow = {
    id: string;
    target: string;
    source: string;
    transforms: string[];
    null_value: string;
};

export type RequestPathRow = {
    id: string;
    name: string;
    target_table: string;
    fallback_path: string;
    unique_by: string;
    include_store_in_id: boolean;
    initial_days: string;
    changed_since: string;
    start: string;
    end: string;
    field_map: FieldMapRow[];
};

export type FieldMapTableOption = {
    label: string;
    columns: string[];
};
