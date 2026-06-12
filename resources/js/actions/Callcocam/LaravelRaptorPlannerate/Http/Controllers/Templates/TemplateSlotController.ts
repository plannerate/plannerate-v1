import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::index
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
export const index = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/slots',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::index
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
index.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return index.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::index
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
index.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::index
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
index.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::index
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
const indexForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::index
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
indexForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::index
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
indexForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::review
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
export const review = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(args, options),
    method: 'get',
})

review.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/review',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::review
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
review.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return review.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::review
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
review.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::review
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
review.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: review.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::review
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
const reviewForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::review
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
reviewForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::review
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
reviewForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

review.form = reviewForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotProducts
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
export const slotProducts = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: slotProducts.url(args, options),
    method: 'get',
})

slotProducts.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/slots/products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotProducts
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
slotProducts.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return slotProducts.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotProducts
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
slotProducts.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: slotProducts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotProducts
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
slotProducts.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: slotProducts.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotProducts
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
const slotProductsForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: slotProducts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotProducts
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
slotProductsForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: slotProducts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotProducts
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
slotProductsForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: slotProducts.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

slotProducts.form = slotProductsForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotAnalysis
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:264
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
export const slotAnalysis = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: slotAnalysis.url(args, options),
    method: 'get',
})

slotAnalysis.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/slots/analysis',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotAnalysis
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:264
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
slotAnalysis.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return slotAnalysis.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotAnalysis
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:264
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
slotAnalysis.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: slotAnalysis.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotAnalysis
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:264
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
slotAnalysis.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: slotAnalysis.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotAnalysis
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:264
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
const slotAnalysisForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: slotAnalysis.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotAnalysis
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:264
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
slotAnalysisForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: slotAnalysis.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::slotAnalysis
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:264
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
slotAnalysisForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: slotAnalysis.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

slotAnalysis.form = slotAnalysisForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::reorder
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:220
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
export const reorder = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(args, options),
    method: 'post',
})

reorder.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/slots/reorder',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::reorder
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:220
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
reorder.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return reorder.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::reorder
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:220
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
reorder.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::reorder
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:220
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
const reorderForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorder.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::reorder
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:220
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
reorderForm.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorder.url(args, options),
    method: 'post',
})

reorder.form = reorderForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::syncImages
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:296
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
export const syncImages = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncImages.url(args, options),
    method: 'post',
})

syncImages.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/slots/sync-images',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::syncImages
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:296
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
syncImages.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return syncImages.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::syncImages
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:296
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
syncImages.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncImages.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::syncImages
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:296
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
const syncImagesForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncImages.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::syncImages
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:296
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
syncImagesForm.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncImages.url(args, options),
    method: 'post',
})

syncImages.form = syncImagesForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:194
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
export const updateSlot = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSlot.url(args, options),
    method: 'put',
})

updateSlot.definition = {
    methods: ["put"],
    url: '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:194
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
updateSlot.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramTemplateSlot: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramTemplateSlot: typeof args.planogramTemplateSlot === 'object'
        ? args.planogramTemplateSlot.id
        : args.planogramTemplateSlot,
    }

    return updateSlot.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramTemplateSlot}', parsedArgs.planogramTemplateSlot.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:194
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
updateSlot.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSlot.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:194
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
const updateSlotForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateSlot.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:194
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
updateSlotForm.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateSlot.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

updateSlot.form = updateSlotForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:207
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
export const destroySlot = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySlot.url(args, options),
    method: 'delete',
})

destroySlot.definition = {
    methods: ["delete"],
    url: '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:207
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
destroySlot.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramTemplateSlot: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramTemplateSlot: typeof args.planogramTemplateSlot === 'object'
        ? args.planogramTemplateSlot.id
        : args.planogramTemplateSlot,
    }

    return destroySlot.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramTemplateSlot}', parsedArgs.planogramTemplateSlot.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:207
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
destroySlot.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySlot.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:207
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
const destroySlotForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroySlot.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:207
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
destroySlotForm.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroySlot.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroySlot.form = destroySlotForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::createSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
export const createSubtemplate = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createSubtemplate.url(args, options),
    method: 'post',
})

createSubtemplate.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::createSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
createSubtemplate.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return createSubtemplate.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::createSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
createSubtemplate.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createSubtemplate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::createSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
const createSubtemplateForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: createSubtemplate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::createSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
createSubtemplateForm.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: createSubtemplate.url(args, options),
    method: 'post',
})

createSubtemplate.form = createSubtemplateForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::cloneSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
export const cloneSubtemplate = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cloneSubtemplate.url(args, options),
    method: 'post',
})

cloneSubtemplate.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::cloneSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
cloneSubtemplate.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramSubtemplate: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramSubtemplate: typeof args.planogramSubtemplate === 'object'
        ? args.planogramSubtemplate.id
        : args.planogramSubtemplate,
    }

    return cloneSubtemplate.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::cloneSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
cloneSubtemplate.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cloneSubtemplate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::cloneSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
const cloneSubtemplateForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cloneSubtemplate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::cloneSubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
cloneSubtemplateForm.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cloneSubtemplate.url(args, options),
    method: 'post',
})

cloneSubtemplate.form = cloneSubtemplateForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::storeSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:160
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
export const storeSlot = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeSlot.url(args, options),
    method: 'post',
})

storeSlot.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::storeSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:160
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
storeSlot.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramSubtemplate: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramSubtemplate: typeof args.planogramSubtemplate === 'object'
        ? args.planogramSubtemplate.id
        : args.planogramSubtemplate,
    }

    return storeSlot.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::storeSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:160
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
storeSlot.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeSlot.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::storeSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:160
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
const storeSlotForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeSlot.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::storeSlot
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:160
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
storeSlotForm.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeSlot.url(args, options),
    method: 'post',
})

storeSlot.form = storeSlotForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::bulkStoreSlots
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:177
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
export const bulkStoreSlots = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkStoreSlots.url(args, options),
    method: 'post',
})

bulkStoreSlots.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::bulkStoreSlots
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:177
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
bulkStoreSlots.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramSubtemplate: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramSubtemplate: typeof args.planogramSubtemplate === 'object'
        ? args.planogramSubtemplate.id
        : args.planogramSubtemplate,
    }

    return bulkStoreSlots.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::bulkStoreSlots
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:177
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
bulkStoreSlots.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkStoreSlots.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::bulkStoreSlots
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:177
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
const bulkStoreSlotsForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: bulkStoreSlots.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::bulkStoreSlots
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:177
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
bulkStoreSlotsForm.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: bulkStoreSlots.url(args, options),
    method: 'post',
})

bulkStoreSlots.form = bulkStoreSlotsForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSubtemplateSlotDefaults
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
*/
export const updateSubtemplateSlotDefaults = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSubtemplateSlotDefaults.url(args, options),
    method: 'put',
})

updateSubtemplateSlotDefaults.definition = {
    methods: ["put"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSubtemplateSlotDefaults
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
*/
updateSubtemplateSlotDefaults.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramSubtemplate: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramSubtemplate: typeof args.planogramSubtemplate === 'object'
        ? args.planogramSubtemplate.id
        : args.planogramSubtemplate,
    }

    return updateSubtemplateSlotDefaults.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSubtemplateSlotDefaults
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
*/
updateSubtemplateSlotDefaults.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSubtemplateSlotDefaults.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSubtemplateSlotDefaults
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
*/
const updateSubtemplateSlotDefaultsForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateSubtemplateSlotDefaults.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::updateSubtemplateSlotDefaults
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
*/
updateSubtemplateSlotDefaultsForm.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateSubtemplateSlotDefaults.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

updateSubtemplateSlotDefaults.form = updateSubtemplateSlotDefaultsForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
export const destroySubtemplate = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySubtemplate.url(args, options),
    method: 'delete',
})

destroySubtemplate.definition = {
    methods: ["delete"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
destroySubtemplate.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramSubtemplate: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramSubtemplate: typeof args.planogramSubtemplate === 'object'
        ? args.planogramSubtemplate.id
        : args.planogramSubtemplate,
    }

    return destroySubtemplate.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
destroySubtemplate.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySubtemplate.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
const destroySubtemplateForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroySubtemplate.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroySubtemplate
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
destroySubtemplateForm.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroySubtemplate.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroySubtemplate.form = destroySubtemplateForm

const TemplateSlotController = { index, review, slotProducts, slotAnalysis, reorder, syncImages, updateSlot, destroySlot, createSubtemplate, cloneSubtemplate, storeSlot, bulkStoreSlots, updateSubtemplateSlotDefaults, destroySubtemplate }

export default TemplateSlotController