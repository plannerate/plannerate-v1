import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
export const abc = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: abc.url(args, options),
    method: 'get',
})

abc.definition = {
    methods: ["get","head"],
    url: '/gondolas/{gondola}/analysis/abc',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
abc.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return abc.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
abc.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: abc.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
abc.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: abc.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
const abcForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: abc.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
abcForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: abc.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/abc'
*/
abcForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: abc.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

abc.form = abcForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
export const targetStock = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: targetStock.url(args, options),
    method: 'get',
})

targetStock.definition = {
    methods: ["get","head"],
    url: '/gondolas/{gondola}/analysis/target-stock',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
targetStock.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return targetStock.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
targetStock.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: targetStock.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
targetStock.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: targetStock.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
const targetStockForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: targetStock.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
targetStockForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: targetStock.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:0
* @route '/gondolas/{gondola}/analysis/target-stock'
*/
targetStockForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: targetStock.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

targetStock.form = targetStockForm

const analysis = {
    abc: Object.assign(abc, abc),
    targetStock: Object.assign(targetStock, targetStock),
}

export default analysis