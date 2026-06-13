import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
export const calculateAbc = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calculateAbc.url(args, options),
    method: 'get',
})

calculateAbc.definition = {
    methods: ["get","head"],
    url: '/gondolas/{gondola}/analysis/abc',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
calculateAbc.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return calculateAbc.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
calculateAbc.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calculateAbc.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
calculateAbc.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: calculateAbc.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
const calculateAbcForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: calculateAbc.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
calculateAbcForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: calculateAbc.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
calculateAbcForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: calculateAbc.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

calculateAbc.form = calculateAbcForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
export const calculateTargetStock = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calculateTargetStock.url(args, options),
    method: 'get',
})

calculateTargetStock.definition = {
    methods: ["get","head"],
    url: '/gondolas/{gondola}/analysis/target-stock',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStock.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return calculateTargetStock.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStock.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calculateTargetStock.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStock.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: calculateTargetStock.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
const calculateTargetStockForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: calculateTargetStock.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStockForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: calculateTargetStock.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStockForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: calculateTargetStock.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

calculateTargetStock.form = calculateTargetStockForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbcApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:22
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
export const calculateAbcApi = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: calculateAbcApi.url(args, options),
    method: 'post',
})

calculateAbcApi.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/analysis/abc',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbcApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:22
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
calculateAbcApi.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return calculateAbcApi.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbcApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:22
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
calculateAbcApi.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: calculateAbcApi.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbcApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:22
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
const calculateAbcApiForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: calculateAbcApi.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateAbcApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:22
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
calculateAbcApiForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: calculateAbcApi.url(args, options),
    method: 'post',
})

calculateAbcApi.form = calculateAbcApiForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStockApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:81
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
export const calculateTargetStockApi = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: calculateTargetStockApi.url(args, options),
    method: 'post',
})

calculateTargetStockApi.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/analysis/target-stock',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStockApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:81
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStockApi.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return calculateTargetStockApi.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStockApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:81
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStockApi.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: calculateTargetStockApi.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStockApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:81
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
const calculateTargetStockApiForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: calculateTargetStockApi.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculateTargetStockApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:81
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
calculateTargetStockApiForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: calculateTargetStockApi.url(args, options),
    method: 'post',
})

calculateTargetStockApi.form = calculateTargetStockApiForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculatePaperApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:144
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
export const calculatePaperApi = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: calculatePaperApi.url(args, options),
    method: 'post',
})

calculatePaperApi.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/analysis/paper',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculatePaperApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:144
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
calculatePaperApi.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return calculatePaperApi.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculatePaperApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:144
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
calculatePaperApi.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: calculatePaperApi.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculatePaperApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:144
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
const calculatePaperApiForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: calculatePaperApi.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::calculatePaperApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:144
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
calculatePaperApiForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: calculatePaperApi.url(args, options),
    method: 'post',
})

calculatePaperApi.form = calculatePaperApiForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clearAnalysisApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:198
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
export const clearAnalysisApi = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clearAnalysisApi.url(args, options),
    method: 'delete',
})

clearAnalysisApi.definition = {
    methods: ["delete"],
    url: '/api/editor/gondolas/{gondola}/analysis',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clearAnalysisApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:198
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
clearAnalysisApi.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return clearAnalysisApi.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clearAnalysisApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:198
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
clearAnalysisApi.delete = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clearAnalysisApi.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clearAnalysisApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:198
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
const clearAnalysisApiForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clearAnalysisApi.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clearAnalysisApi
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:198
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
clearAnalysisApiForm.delete = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clearAnalysisApi.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

clearAnalysisApi.form = clearAnalysisApiForm

const GondolaAnalysisController = { calculateAbc, calculateTargetStock, calculateAbcApi, calculateTargetStockApi, calculatePaperApi, clearAnalysisApi }

export default GondolaAnalysisController