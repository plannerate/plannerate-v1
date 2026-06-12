import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportAbcCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
export const exportAbcCsv = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportAbcCsv.url(args, options),
    method: 'get',
})

exportAbcCsv.definition = {
    methods: ["get","head"],
    url: '/api/editor/gondolas/{gondola}/analysis/abc/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportAbcCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportAbcCsv.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return exportAbcCsv.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportAbcCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportAbcCsv.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportAbcCsv.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportAbcCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportAbcCsv.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportAbcCsv.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportAbcCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
const exportAbcCsvForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportAbcCsv.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportAbcCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportAbcCsvForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportAbcCsv.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportAbcCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportAbcCsvForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportAbcCsv.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportAbcCsv.form = exportAbcCsvForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportStockCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:39
* @route '/api/editor/gondolas/{gondola}/analysis/stock/export'
*/
export const exportStockCsv = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportStockCsv.url(args, options),
    method: 'get',
})

exportStockCsv.definition = {
    methods: ["get","head"],
    url: '/api/editor/gondolas/{gondola}/analysis/stock/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportStockCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:39
* @route '/api/editor/gondolas/{gondola}/analysis/stock/export'
*/
exportStockCsv.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return exportStockCsv.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportStockCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:39
* @route '/api/editor/gondolas/{gondola}/analysis/stock/export'
*/
exportStockCsv.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportStockCsv.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportStockCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:39
* @route '/api/editor/gondolas/{gondola}/analysis/stock/export'
*/
exportStockCsv.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportStockCsv.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportStockCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:39
* @route '/api/editor/gondolas/{gondola}/analysis/stock/export'
*/
const exportStockCsvForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportStockCsv.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportStockCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:39
* @route '/api/editor/gondolas/{gondola}/analysis/stock/export'
*/
exportStockCsvForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportStockCsv.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportStockCsv
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:39
* @route '/api/editor/gondolas/{gondola}/analysis/stock/export'
*/
exportStockCsvForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportStockCsv.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportStockCsv.form = exportStockCsvForm

const AnalysisExportController = { exportAbcCsv, exportStockCsv }

export default AnalysisExportController