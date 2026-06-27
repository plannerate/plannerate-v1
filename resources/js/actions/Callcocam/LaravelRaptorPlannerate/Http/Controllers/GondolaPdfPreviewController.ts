import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:30
* @route '/export/gondola/{gondola}/view'
*/
export const show = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/export/gondola/{gondola}/view',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:30
* @route '/export/gondola/{gondola}/view'
*/
show.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:30
* @route '/export/gondola/{gondola}/view'
*/
show.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:30
* @route '/export/gondola/{gondola}/view'
*/
show.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:30
* @route '/export/gondola/{gondola}/view'
*/
const showForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:30
* @route '/export/gondola/{gondola}/view'
*/
showForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:30
* @route '/export/gondola/{gondola}/view'
*/
showForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const GondolaPdfPreviewController = { show }

export default GondolaPdfPreviewController