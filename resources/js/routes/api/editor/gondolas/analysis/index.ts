import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
import abc06b05a from './abc'
import stock from './stock'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:24
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
export const abc = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: abc.url(args, options),
    method: 'post',
})

abc.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/analysis/abc',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:24
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:24
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
abc.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: abc.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:24
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
const abcForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: abc.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::abc
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:24
* @route '/api/editor/gondolas/{gondola}/analysis/abc'
*/
abcForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: abc.url(args, options),
    method: 'post',
})

abc.form = abcForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:83
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
export const targetStock = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: targetStock.url(args, options),
    method: 'post',
})

targetStock.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/analysis/target-stock',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:83
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:83
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
targetStock.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: targetStock.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:83
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
const targetStockForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: targetStock.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::targetStock
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:83
* @route '/api/editor/gondolas/{gondola}/analysis/target-stock'
*/
targetStockForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: targetStock.url(args, options),
    method: 'post',
})

targetStock.form = targetStockForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::paper
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:146
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
export const paper = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: paper.url(args, options),
    method: 'post',
})

paper.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/analysis/paper',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::paper
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:146
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
paper.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return paper.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::paper
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:146
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
paper.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: paper.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::paper
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:146
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
const paperForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: paper.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::paper
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:146
* @route '/api/editor/gondolas/{gondola}/analysis/paper'
*/
paperForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: paper.url(args, options),
    method: 'post',
})

paper.form = paperForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::bcg
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:207
* @route '/api/editor/gondolas/{gondola}/analysis/bcg'
*/
export const bcg = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bcg.url(args, options),
    method: 'post',
})

bcg.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/analysis/bcg',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::bcg
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:207
* @route '/api/editor/gondolas/{gondola}/analysis/bcg'
*/
bcg.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return bcg.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::bcg
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:207
* @route '/api/editor/gondolas/{gondola}/analysis/bcg'
*/
bcg.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bcg.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::bcg
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:207
* @route '/api/editor/gondolas/{gondola}/analysis/bcg'
*/
const bcgForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: bcg.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::bcg
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:207
* @route '/api/editor/gondolas/{gondola}/analysis/bcg'
*/
bcgForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: bcg.url(args, options),
    method: 'post',
})

bcg.form = bcgForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clear
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:269
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
export const clear = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clear.url(args, options),
    method: 'delete',
})

clear.definition = {
    methods: ["delete"],
    url: '/api/editor/gondolas/{gondola}/analysis',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clear
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:269
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
clear.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return clear.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clear
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:269
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
clear.delete = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clear.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clear
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:269
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
const clearForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clear.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController::clear
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php:269
* @route '/api/editor/gondolas/{gondola}/analysis'
*/
clearForm.delete = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clear.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

clear.form = clearForm

const analysis = {
    abc: Object.assign(abc, abc06b05a),
    targetStock: Object.assign(targetStock, targetStock),
    paper: Object.assign(paper, paper),
    bcg: Object.assign(bcg, bcg),
    clear: Object.assign(clear, clear),
    stock: Object.assign(stock, stock),
}

export default analysis