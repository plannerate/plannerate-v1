import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportMethod
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
export const exportMethod = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/api/editor/gondolas/{gondola}/analysis/abc/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportMethod
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportMethod.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return exportMethod.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportMethod
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportMethod.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportMethod
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportMethod.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportMethod
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
const exportMethodForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportMethod
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportMethodForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\AnalysisExportController::exportMethod
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/AnalysisExportController.php:29
* @route '/api/editor/gondolas/{gondola}/analysis/abc/export'
*/
exportMethodForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportMethod.form = exportMethodForm

const abc = {
    export: Object.assign(exportMethod, exportMethod),
}

export default abc