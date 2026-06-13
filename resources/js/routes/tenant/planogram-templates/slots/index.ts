import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:31
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:31
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
index.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
index.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
const indexForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:31
* @route '/planogram-templates/{planogramTemplate}/slots'
*/
indexForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:31
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::review
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:43
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::review
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:43
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::review
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
review.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::review
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
review.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: review.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::review
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
const reviewForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::review
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:43
* @route '/planogram-templates/{planogramTemplate}/review'
*/
reviewForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::review
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:43
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:250
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
export const products = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

products.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/slots/products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:250
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
products.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return products.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:250
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
products.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:250
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
products.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: products.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:250
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
const productsForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:250
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
productsForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:250
* @route '/planogram-templates/{planogramTemplate}/slots/products'
*/
productsForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

products.form = productsForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::analysis
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:279
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
export const analysis = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: analysis.url(args, options),
    method: 'get',
})

analysis.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/slots/analysis',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::analysis
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:279
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
analysis.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return analysis.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::analysis
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:279
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
analysis.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: analysis.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::analysis
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:279
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
analysis.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: analysis.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::analysis
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:279
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
const analysisForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: analysis.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::analysis
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:279
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
analysisForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: analysis.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::analysis
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:279
* @route '/planogram-templates/{planogramTemplate}/slots/analysis'
*/
analysisForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: analysis.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

analysis.form = analysisForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::reorder
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:235
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::reorder
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:235
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::reorder
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
reorder.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::reorder
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
const reorderForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorder.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::reorder
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:235
* @route '/planogram-templates/{planogramTemplate}/slots/reorder'
*/
reorderForm.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorder.url(args, options),
    method: 'post',
})

reorder.form = reorderForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::syncImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:311
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::syncImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:311
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::syncImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:311
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
syncImages.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncImages.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::syncImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:311
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
const syncImagesForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncImages.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::syncImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:311
* @route '/planogram-templates/{planogramTemplate}/slots/sync-images'
*/
syncImagesForm.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncImages.url(args, options),
    method: 'post',
})

syncImages.form = syncImagesForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:209
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
export const update = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}',
} satisfies RouteDefinition<["put"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:209
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
update.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramTemplateSlot}', parsedArgs.planogramTemplateSlot.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:209
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
update.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:209
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
const updateForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:209
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
updateForm.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:222
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
export const destroy = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:222
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
destroy.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramTemplateSlot}', parsedArgs.planogramTemplateSlot.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:222
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
destroy.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:222
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
const destroyForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:222
* @route '/planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}'
*/
destroyForm.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramTemplateSlot: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:175
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
export const store = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:175
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
store.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:175
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
store.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:175
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
const storeForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:175
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots'
*/
storeForm.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::bulk
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:192
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
export const bulk = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulk.url(args, options),
    method: 'post',
})

bulk.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::bulk
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:192
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
bulk.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return bulk.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::bulk
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:192
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
bulk.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulk.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::bulk
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:192
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
const bulkForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: bulk.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::bulk
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:192
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk'
*/
bulkForm.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: bulk.url(args, options),
    method: 'post',
})

bulk.form = bulkForm

const slots = {
    index: Object.assign(index, index),
    review: Object.assign(review, review),
    products: Object.assign(products, products),
    analysis: Object.assign(analysis, analysis),
    reorder: Object.assign(reorder, reorder),
    syncImages: Object.assign(syncImages, syncImages),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    store: Object.assign(store, store),
    bulk: Object.assign(bulk, bulk),
}

export default slots