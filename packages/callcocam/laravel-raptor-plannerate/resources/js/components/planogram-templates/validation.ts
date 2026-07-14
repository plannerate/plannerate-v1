import { z } from 'zod';

export const slotDraftSchema = z
    .object({
        module_number: z
            .number('Módulo deve ser um número.')
            .int()
            .min(1, 'Módulo mínimo é 1.')
            .max(20, 'Módulo máximo é 20.'),
        shelf_order: z
            .number('Prateleira deve ser um número.')
            .int()
            .min(1, 'Prateleira mínima é 1.')
            .max(10, 'Prateleira máxima é 10.'),
        category_id: z.string().min(1, 'Selecione uma categoria para este slot.').nullable(),
        min_facings: z
            .number('Frentes mínimas deve ser um número.')
            .int()
            .min(1, 'Frentes mínimas deve ser pelo menos 1.')
            .max(20, 'Frentes mínimas não pode exceder 20.'),
        max_facings: z
            .number('Frentes máximas deve ser um número.')
            .int()
            .min(1, 'Frentes máximas deve ser pelo menos 1.')
            .max(20, 'Frentes máximas não pode exceder 20.'),
        priority: z
            .number('Prioridade deve ser um número.')
            .int()
            .min(1, 'Prioridade mínima é 1.')
            .max(10, 'Prioridade máxima é 10.'),
        price_order: z.enum(['asc', 'desc', 'none']),
        size_order: z.enum(['asc', 'desc', 'none']),
        brand_exposure: z.enum(['vertical', 'horizontal', 'mixed']),
        flavor_exposure: z.enum(['vertical', 'horizontal', 'mixed']),
        space_fallback: z.enum(['reduce_c', 'reduce_facings', 'skip', 'remove_dog']),
        use_target_stock: z.boolean(),
        facing_expansion: z.enum(['none', 'score', 'current_stock', 'target_stock', 'equal']),
        role_override: z
            .enum(['destino', 'rotina', 'conveniencia', 'impulso', 'sazonal', 'complementar'])
            .nullable()
            .optional(),
        visual_criteria: z
            .array(
                z.object({
                    key: z.enum(['marca', 'preco', 'tamanho', 'score_abc', 'margem', 'embalagem', 'tipo', 'sabor', 'atributo']),
                    direction: z.enum(['asc', 'desc', 'none']),
                    packaging_order: z.array(z.string()).optional(),
                }),
            )
            .nullable()
            .optional(),
        max_share_per_sku: z
            .number('Limite de SKU deve ser um número.')
            .int()
            .min(1, 'Mínimo 1%.')
            .max(100, 'Máximo 100%.')
            .nullable()
            .optional(),
        max_share_per_brand: z
            .number('Limite de marca deve ser um número.')
            .int()
            .min(1, 'Mínimo 1%.')
            .max(100, 'Máximo 100%.')
            .nullable()
            .optional(),
        max_share_per_subcategory: z
            .number('Limite de subcategoria deve ser um número.')
            .int()
            .min(1, 'Mínimo 1%.')
            .max(100, 'Máximo 100%.')
            .nullable()
            .optional(),
    })
    .refine((data) => data.max_facings >= data.min_facings, {
        message: 'Frentes máximas deve ser maior ou igual às frentes mínimas.',
        path: ['max_facings'],
    })
    .refine((data) => data.category_id !== null && data.category_id !== '', {
        message: 'Selecione uma categoria para este slot.',
        path: ['category_id'],
    })
    .refine(
        (data) => {
            const vc = data.visual_criteria;

            if (!vc || vc.length === 0) {
return true;
}

            return vc[0].key === 'score_abc';
        },
        {
            message: 'O critério "Curva ABC" deve ser sempre o primeiro na lista de ordenação visual.',
            path: ['visual_criteria'],
        },
    );

export type SlotDraftValidated = z.infer<typeof slotDraftSchema>;

export type SlotValidationErrors = Partial<Record<keyof SlotDraftValidated, string>>;

/** Roda safeParse e devolve mapa de erro por campo (primeiro erro de cada). */
export function validateSlotDraft(data: unknown): SlotValidationErrors {
    const result = slotDraftSchema.safeParse(data);

    if (result.success) {
return {};
}

    const errors: SlotValidationErrors = {};

    for (const issue of result.error.issues) {
        const field = issue.path[0] as keyof SlotDraftValidated | undefined;

        if (field && !errors[field]) {
            errors[field] = issue.message;
        }
    }

    return errors;
}

export const moduleDefaultsSchema = z
    .object({
        category_id: z.string().nullable(),
        min_facings: z
            .number('Frentes mínimas deve ser um número.')
            .int()
            .min(1, 'Frentes mínimas deve ser pelo menos 1.')
            .max(20, 'Frentes mínimas não pode exceder 20.'),
        max_facings: z
            .number('Frentes máximas deve ser um número.')
            .int()
            .min(1, 'Frentes máximas deve ser pelo menos 1.')
            .max(20, 'Frentes máximas não pode exceder 20.'),
        priority: z
            .number('Prioridade deve ser um número.')
            .int()
            .min(1, 'Prioridade mínima é 1.')
            .max(10, 'Prioridade máxima é 10.'),
        price_order: z.enum(['asc', 'desc', 'none']),
        size_order: z.enum(['asc', 'desc', 'none']),
        brand_exposure: z.enum(['vertical', 'horizontal', 'mixed']),
        flavor_exposure: z.enum(['vertical', 'horizontal', 'mixed']),
        space_fallback: z.enum(['reduce_c', 'reduce_facings', 'skip', 'remove_dog']),
        use_target_stock: z.boolean(),
        facing_expansion: z.enum(['none', 'score', 'current_stock', 'target_stock', 'equal']),
    })
    .refine((data) => data.max_facings >= data.min_facings, {
        message: 'Frentes máximas deve ser maior ou igual às frentes mínimas.',
        path: ['max_facings'],
    });

export type ModuleDefaultsValidated = z.infer<typeof moduleDefaultsSchema>;

export type ModuleDefaultsValidationErrors = Partial<Record<keyof ModuleDefaultsValidated, string>>;

/** Roda safeParse e devolve mapa de erro por campo (primeiro erro de cada). */
export function validateModuleDefaults(data: unknown): ModuleDefaultsValidationErrors {
    const result = moduleDefaultsSchema.safeParse(data);

    if (result.success) {
return {};
}

    const errors: ModuleDefaultsValidationErrors = {};

    for (const issue of result.error.issues) {
        const field = issue.path[0] as keyof ModuleDefaultsValidated | undefined;

        if (field && !errors[field]) {
            errors[field] = issue.message;
        }
    }

    return errors;
}

const zonePriorityValues = [
    'maior_margem',
    'maior_giro',
    'maior_valor_vendido',
    'curva_a',
    'menor_margem',
    'complementar_fria',
    'maior_volume',
    'menor_prioridade',
] as const;

export const subtemplateSettingsSchema = z.object({
    hot_zone_priority: z.enum(zonePriorityValues).nullable(),
    cold_zone_priority: z.enum(zonePriorityValues).nullable(),
    flow_direction: z.enum(['left_to_right', 'right_to_left']).nullable(),
    layout_orientation: z.enum(['horizontal', 'vertical']).nullable(),
});

export type SubtemplateSettingsValidated = z.infer<typeof subtemplateSettingsSchema>;

export type SubtemplateSettingsValidationErrors = Partial<Record<keyof SubtemplateSettingsValidated, string>>;

/** Roda safeParse e devolve mapa de erro por campo (primeiro erro de cada). */
export function validateSubtemplateSettings(data: unknown): SubtemplateSettingsValidationErrors {
    const result = subtemplateSettingsSchema.safeParse(data);

    if (result.success) {
return {};
}

    const errors: SubtemplateSettingsValidationErrors = {};

    for (const issue of result.error.issues) {
        const field = issue.path[0] as keyof SubtemplateSettingsValidated | undefined;

        if (field && !errors[field]) {
            errors[field] = issue.message;
        }
    }

    return errors;
}
