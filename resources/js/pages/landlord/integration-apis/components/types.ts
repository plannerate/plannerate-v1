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
    /** Colunas atualizadas quando a linha da pivot já existe (além de updated_at). */
    update_columns: string;
};

export type RequestPathRow = {
    id: string;
    target_table: string;
    fallback_path: string;
    id_prefix: string;
    unique_by: string;
    include_store_in_id: boolean;
    initial_days: string;
    chunk_days: string;
    last_date_column: string;
    max_page: string;
    min_page_size: string;
    max_page_size: string;
    changed_since: string;
    start: string;
    end: string;
    /** Caminho dos itens neste endpoint; vazio usa o items_path global da resposta. */
    items_path: string;
    /** Modo cursor: campo do item que guarda o id usado no placeholder {cursor}. */
    cursor_item_path: string;
    /** Modo cursor: valor inicial do cursor (a RP Info usa "0"). */
    cursor_initial: string;
    /** Formato de data exigido na query (ex.: d-m-Y); vazio mantém ISO. */
    date_query_format: string;
    /** Alvos mapeados que alimentam só as pivots (métrica por loja). */
    pivot_only_targets: string;
    field_map: FieldMapRow[];
    pivot_tables: PivotTableRow[];
    validations: ValidationRow[];
};

export type FieldMapTableOption = {
    label: string;
    columns: string[];
};
