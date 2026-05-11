export type FieldMapRow = {
    id: string;
    target: string;
    source: string;
    transforms: string[];
};

export type CalculationRow = {
    id: string;
    target: string;
    operation: string;
    operands: string[];
    transforms: string[];
};

export type RequestPathRow = {
    id: string;
    name: string;
    fallback_path: string;
    changed_since: string;
    start: string;
    end: string;
    field_map: FieldMapRow[];
    calculations: CalculationRow[];
};
