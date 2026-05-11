export type FieldMapRow = {
    id: string;
    target: string;
    source: string;
    transforms: string[];
};

export type RequestPathRow = {
    id: string;
    name: string;
    target_table: string;
    fallback_path: string;
    changed_since: string;
    start: string;
    end: string;
    field_map: FieldMapRow[];
};

export type FieldMapTableOption = {
    label: string;
    columns: string[];
};
