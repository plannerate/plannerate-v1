import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\AutoPlanogramController::generate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
export const generate = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

generate.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/auto-generate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::generate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
generate.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return generate.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::generate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
generate.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::generate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
const generateForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::generate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
generateForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generate.url(args, options),
    method: 'post',
})

generate.form = generateForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::rejectedProducts
* @see app/Http/Controllers/AutoPlanogramController.php:156
* @route '/api/gondolas/{gondola}/rejected-products'
*/
export const rejectedProducts = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: rejectedProducts.url(args, options),
    method: 'get',
})

rejectedProducts.definition = {
    methods: ["get","head"],
    url: '/api/gondolas/{gondola}/rejected-products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::rejectedProducts
* @see app/Http/Controllers/AutoPlanogramController.php:156
* @route '/api/gondolas/{gondola}/rejected-products'
*/
rejectedProducts.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return rejectedProducts.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::rejectedProducts
* @see app/Http/Controllers/AutoPlanogramController.php:156
* @route '/api/gondolas/{gondola}/rejected-products'
*/
rejectedProducts.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: rejectedProducts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::rejectedProducts
* @see app/Http/Controllers/AutoPlanogramController.php:156
* @route '/api/gondolas/{gondola}/rejected-products'
*/
rejectedProducts.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: rejectedProducts.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::rejectedProducts
* @see app/Http/Controllers/AutoPlanogramController.php:156
* @route '/api/gondolas/{gondola}/rejected-products'
*/
const rejectedProductsForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: rejectedProducts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::rejectedProducts
* @see app/Http/Controllers/AutoPlanogramController.php:156
* @route '/api/gondolas/{gondola}/rejected-products'
*/
rejectedProductsForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: rejectedProducts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::rejectedProducts
* @see app/Http/Controllers/AutoPlanogramController.php:156
* @route '/api/gondolas/{gondola}/rejected-products'
*/
rejectedProductsForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: rejectedProducts.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

rejectedProducts.form = rejectedProductsForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::templateGroupings
* @see app/Http/Controllers/AutoPlanogramController.php:187
* @route '/api/gondolas/{gondola}/template-groupings'
*/
export const templateGroupings = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: templateGroupings.url(args, options),
    method: 'get',
})

templateGroupings.definition = {
    methods: ["get","head"],
    url: '/api/gondolas/{gondola}/template-groupings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::templateGroupings
* @see app/Http/Controllers/AutoPlanogramController.php:187
* @route '/api/gondolas/{gondola}/template-groupings'
*/
templateGroupings.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return templateGroupings.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::templateGroupings
* @see app/Http/Controllers/AutoPlanogramController.php:187
* @route '/api/gondolas/{gondola}/template-groupings'
*/
templateGroupings.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: templateGroupings.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::templateGroupings
* @see app/Http/Controllers/AutoPlanogramController.php:187
* @route '/api/gondolas/{gondola}/template-groupings'
*/
templateGroupings.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: templateGroupings.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::templateGroupings
* @see app/Http/Controllers/AutoPlanogramController.php:187
* @route '/api/gondolas/{gondola}/template-groupings'
*/
const templateGroupingsForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: templateGroupings.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::templateGroupings
* @see app/Http/Controllers/AutoPlanogramController.php:187
* @route '/api/gondolas/{gondola}/template-groupings'
*/
templateGroupingsForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: templateGroupings.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::templateGroupings
* @see app/Http/Controllers/AutoPlanogramController.php:187
* @route '/api/gondolas/{gondola}/template-groupings'
*/
templateGroupingsForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: templateGroupings.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

templateGroupings.form = templateGroupingsForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::destroyRejectedProduct
* @see app/Http/Controllers/AutoPlanogramController.php:265
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
export const destroyRejectedProduct = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyRejectedProduct.url(args, options),
    method: 'delete',
})

destroyRejectedProduct.definition = {
    methods: ["delete"],
    url: '/api/gondolas/{gondola}/rejected-products/{rejected}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::destroyRejectedProduct
* @see app/Http/Controllers/AutoPlanogramController.php:265
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
destroyRejectedProduct.url = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
            rejected: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
        rejected: args.rejected,
    }

    return destroyRejectedProduct.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace('{rejected}', parsedArgs.rejected.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::destroyRejectedProduct
* @see app/Http/Controllers/AutoPlanogramController.php:265
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
destroyRejectedProduct.delete = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyRejectedProduct.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::destroyRejectedProduct
* @see app/Http/Controllers/AutoPlanogramController.php:265
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
const destroyRejectedProductForm = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroyRejectedProduct.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::destroyRejectedProduct
* @see app/Http/Controllers/AutoPlanogramController.php:265
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
destroyRejectedProductForm.delete = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroyRejectedProduct.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroyRejectedProduct.form = destroyRejectedProductForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::swapProduct
* @see app/Http/Controllers/AutoPlanogramController.php:443
* @route '/api/gondolas/{gondola}/swap-product'
*/
export const swapProduct = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: swapProduct.url(args, options),
    method: 'post',
})

swapProduct.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/swap-product',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::swapProduct
* @see app/Http/Controllers/AutoPlanogramController.php:443
* @route '/api/gondolas/{gondola}/swap-product'
*/
swapProduct.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return swapProduct.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::swapProduct
* @see app/Http/Controllers/AutoPlanogramController.php:443
* @route '/api/gondolas/{gondola}/swap-product'
*/
swapProduct.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: swapProduct.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::swapProduct
* @see app/Http/Controllers/AutoPlanogramController.php:443
* @route '/api/gondolas/{gondola}/swap-product'
*/
const swapProductForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: swapProduct.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::swapProduct
* @see app/Http/Controllers/AutoPlanogramController.php:443
* @route '/api/gondolas/{gondola}/swap-product'
*/
swapProductForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: swapProduct.url(args, options),
    method: 'post',
})

swapProduct.form = swapProductForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderVisual
* @see app/Http/Controllers/AutoPlanogramController.php:278
* @route '/api/gondolas/{gondola}/reorder-visual'
*/
export const reorderVisual = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorderVisual.url(args, options),
    method: 'post',
})

reorderVisual.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/reorder-visual',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderVisual
* @see app/Http/Controllers/AutoPlanogramController.php:278
* @route '/api/gondolas/{gondola}/reorder-visual'
*/
reorderVisual.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return reorderVisual.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderVisual
* @see app/Http/Controllers/AutoPlanogramController.php:278
* @route '/api/gondolas/{gondola}/reorder-visual'
*/
reorderVisual.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorderVisual.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderVisual
* @see app/Http/Controllers/AutoPlanogramController.php:278
* @route '/api/gondolas/{gondola}/reorder-visual'
*/
const reorderVisualForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorderVisual.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderVisual
* @see app/Http/Controllers/AutoPlanogramController.php:278
* @route '/api/gondolas/{gondola}/reorder-visual'
*/
reorderVisualForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorderVisual.url(args, options),
    method: 'post',
})

reorderVisual.form = reorderVisualForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeExposure
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
export const redistributeExposure = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistributeExposure.url(args, options),
    method: 'post',
})

redistributeExposure.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/redistribute',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeExposure
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
redistributeExposure.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return redistributeExposure.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeExposure
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
redistributeExposure.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistributeExposure.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeExposure
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
const redistributeExposureForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistributeExposure.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeExposure
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
redistributeExposureForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistributeExposure.url(args, options),
    method: 'post',
})

redistributeExposure.form = redistributeExposureForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderGondola
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
export const reorderGondola = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorderGondola.url(args, options),
    method: 'post',
})

reorderGondola.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/reorder-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderGondola
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
reorderGondola.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return reorderGondola.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderGondola
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
reorderGondola.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorderGondola.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderGondola
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
const reorderGondolaForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorderGondola.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderGondola
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
reorderGondolaForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorderGondola.url(args, options),
    method: 'post',
})

reorderGondola.form = reorderGondolaForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeGondola
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
export const redistributeGondola = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistributeGondola.url(args, options),
    method: 'post',
})

redistributeGondola.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/redistribute-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeGondola
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
redistributeGondola.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return redistributeGondola.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeGondola
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
redistributeGondola.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistributeGondola.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeGondola
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
const redistributeGondolaForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistributeGondola.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeGondola
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
redistributeGondolaForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistributeGondola.url(args, options),
    method: 'post',
})

redistributeGondola.form = redistributeGondolaForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::regenerateAuto
* @see app/Http/Controllers/AutoPlanogramController.php:395
* @route '/api/gondolas/{gondola}/regenerate-auto'
*/
export const regenerateAuto = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: regenerateAuto.url(args, options),
    method: 'post',
})

regenerateAuto.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/regenerate-auto',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::regenerateAuto
* @see app/Http/Controllers/AutoPlanogramController.php:395
* @route '/api/gondolas/{gondola}/regenerate-auto'
*/
regenerateAuto.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return regenerateAuto.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::regenerateAuto
* @see app/Http/Controllers/AutoPlanogramController.php:395
* @route '/api/gondolas/{gondola}/regenerate-auto'
*/
regenerateAuto.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: regenerateAuto.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::regenerateAuto
* @see app/Http/Controllers/AutoPlanogramController.php:395
* @route '/api/gondolas/{gondola}/regenerate-auto'
*/
const regenerateAutoForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: regenerateAuto.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::regenerateAuto
* @see app/Http/Controllers/AutoPlanogramController.php:395
* @route '/api/gondolas/{gondola}/regenerate-auto'
*/
regenerateAutoForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: regenerateAuto.url(args, options),
    method: 'post',
})

regenerateAuto.form = regenerateAutoForm

const AutoPlanogramController = { generate, rejectedProducts, templateGroupings, destroyRejectedProduct, swapProduct, reorderVisual, redistributeExposure, reorderGondola, redistributeGondola, regenerateAuto }

export default AutoPlanogramController