import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
export const index = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/editor/gondolas/{gondola}/sales/indicators',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
index.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
index.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
index.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
const indexForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
indexForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
indexForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

const GondolaSalesIndicatorController = { index }

export default GondolaSalesIndicatorController