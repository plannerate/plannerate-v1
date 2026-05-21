import { z } from 'zod';

export const slotDraftSchema = z
    .object({
        module_number: z.number().int().min(1).max(6),
        shelf_order: z.number().int().min(1).max(10),
        category_id: z.string().min(1, 'Selecione uma categoria para este slot.').nullable(),
        min_facings: z
            .number({ invalid_type_error: 'Frentes mínimas deve ser um número.' })
            .int()
            .min(1, 'Frentes mínimas deve ser pelo menos 1.')
            .max(20, 'Frentes mínimas não pode exceder 20.'),
        max_facings: z
            .number({ invalid_type_error: 'Frentes máximas deve ser um número.' })
            .int()
            .min(1, 'Frentes máximas deve ser pelo menos 1.')
            .max(20, 'Frentes máximas não pode exceder 20.'),
        priority: z
            .number({ invalid_type_error: 'Prioridade deve ser um número.' })
            .int()
            .min(1, 'Prioridade mínima é 1.')
            .max(10, 'Prioridade máxima é 10.'),
        price_order: z.enum(['asc', 'desc', 'none']),
        size_order: z.enum(['asc', 'desc', 'none']),
        brand_exposure: z.enum(['vertical', 'horizontal', 'mixed']),
        flavor_exposure: z.enum(['vertical', 'horizontal', 'mixed']),
        space_fallback: z.enum(['reduce_c', 'reduce_facings', 'skip']),
        use_target_stock: z.boolean(),
        facing_expansion: z.enum(['none', 'score', 'current_stock', 'target_stock', 'equal']),
        role_override: z
            .enum(['destino', 'rotina', 'conveniencia', 'impulso', 'sazonal', 'complementar'])
            .nullable()
            .optional(),
        visual_criteria: z
            .array(
                z.object({
                    key: z.enum(['marca', 'preco', 'tamanho', 'score_abc', 'margem', 'embalagem']),
                    direction: z.enum(['asc', 'desc', 'none']),
                    packaging_order: z.array(z.string()).optional(),
                }),
            )
            .nullable()
            .optional(),
        max_share_per_sku: z
            .number({ invalid_type_error: 'Limite de SKU deve ser um número.' })
            .int()
            .min(1, 'Mínimo 1%.')
            .max(100, 'Máximo 100%.')
            .nullable()
            .optional(),
        max_share_per_brand: z
            .number({ invalid_type_error: 'Limite de marca deve ser um número.' })
            .int()
            .min(1, 'Mínimo 1%.')
            .max(100, 'Máximo 100%.')
            .nullable()
            .optional(),
        max_share_per_subcategory: z
            .number({ invalid_type_error: 'Limite de subcategoria deve ser um número.' })
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
    });

export type SlotDraftValidated = z.infer<typeof slotDraftSchema>;

export type SlotValidationErrors = Partial<Record<keyof SlotDraftValidated, string>>;

/** Roda safeParse e devolve mapa de erro por campo (primeiro erro de cada). */
export function validateSlotDraft(data: unknown): SlotValidationErrors {
    const result = slotDraftSchema.safeParse(data);
    if (result.success) return {};

    const errors: SlotValidationErrors = {};
    for (const issue of result.error.issues) {
        const field = issue.path[0] as keyof SlotDraftValidated | undefined;
        if (field && !errors[field]) {
            errors[field] = issue.message;
        }
    }
    return errors;
}
