import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import rejectedProducts39b35f from './rejected-products'
import generationOverrides from './generation-overrides'
/**
* @see \App\Http\Controllers\AutoPlanogramController::autoGenerate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
export const autoGenerate = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: autoGenerate.url(args, options),
    method: 'post',
})

autoGenerate.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/auto-generate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::autoGenerate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
autoGenerate.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return autoGenerate.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::autoGenerate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
autoGenerate.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: autoGenerate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::autoGenerate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
const autoGenerateForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: autoGenerate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::autoGenerate
* @see app/Http/Controllers/AutoPlanogramController.php:35
* @route '/api/gondolas/{gondola}/auto-generate'
*/
autoGenerateForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: autoGenerate.url(args, options),
    method: 'post',
})

autoGenerate.form = autoGenerateForm

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
* @see \App\Http\Controllers\AutoPlanogramController::redistribute
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
export const redistribute = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistribute.url(args, options),
    method: 'post',
})

redistribute.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/redistribute',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistribute
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
redistribute.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return redistribute.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistribute
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
redistribute.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistribute.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistribute
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
const redistributeForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistribute.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistribute
* @see app/Http/Controllers/AutoPlanogramController.php:300
* @route '/api/gondolas/{gondola}/redistribute'
*/
redistributeForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistribute.url(args, options),
    method: 'post',
})

redistribute.form = redistributeForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderAll
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
export const reorderAll = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorderAll.url(args, options),
    method: 'post',
})

reorderAll.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/reorder-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderAll
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
reorderAll.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return reorderAll.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderAll
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
reorderAll.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorderAll.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderAll
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
const reorderAllForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorderAll.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::reorderAll
* @see app/Http/Controllers/AutoPlanogramController.php:322
* @route '/api/gondolas/{gondola}/reorder-all'
*/
reorderAllForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorderAll.url(args, options),
    method: 'post',
})

reorderAll.form = reorderAllForm

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeAll
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
export const redistributeAll = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistributeAll.url(args, options),
    method: 'post',
})

redistributeAll.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/redistribute-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeAll
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
redistributeAll.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return redistributeAll.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeAll
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
redistributeAll.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: redistributeAll.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeAll
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
const redistributeAllForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistributeAll.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AutoPlanogramController::redistributeAll
* @see app/Http/Controllers/AutoPlanogramController.php:344
* @route '/api/gondolas/{gondola}/redistribute-all'
*/
redistributeAllForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: redistributeAll.url(args, options),
    method: 'post',
})

redistributeAll.form = redistributeAllForm

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

const gondolas = {
    autoGenerate: Object.assign(autoGenerate, autoGenerate),
    rejectedProducts: Object.assign(rejectedProducts, rejectedProducts39b35f),
    templateGroupings: Object.assign(templateGroupings, templateGroupings),
    swapProduct: Object.assign(swapProduct, swapProduct),
    reorderVisual: Object.assign(reorderVisual, reorderVisual),
    redistribute: Object.assign(redistribute, redistribute),
    reorderAll: Object.assign(reorderAll, reorderAll),
    redistributeAll: Object.assign(redistributeAll, redistributeAll),
    regenerateAuto: Object.assign(regenerateAuto, regenerateAuto),
    generationOverrides: Object.assign(generationOverrides, generationOverrides),
}

export default gondolas