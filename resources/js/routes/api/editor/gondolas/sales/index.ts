import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::indicators
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
export const indicators = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indicators.url(args, options),
    method: 'get',
})

indicators.definition = {
    methods: ["get","head"],
    url: '/api/editor/gondolas/{gondola}/sales/indicators',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::indicators
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
indicators.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return indicators.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::indicators
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
indicators.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indicators.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::indicators
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
indicators.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: indicators.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::indicators
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
const indicatorsForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indicators.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::indicators
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
indicatorsForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indicators.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaSalesIndicatorController::indicators
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaSalesIndicatorController.php:27
* @route '/api/editor/gondolas/{gondola}/sales/indicators'
*/
indicatorsForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indicators.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

indicators.form = indicatorsForm

const sales = {
    indicators: Object.assign(indicators, indicators),
}

export default sales