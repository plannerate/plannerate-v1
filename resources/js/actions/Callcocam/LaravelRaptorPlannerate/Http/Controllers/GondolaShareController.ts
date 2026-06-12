import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
export const show = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/gondola/{gondolaId}/share',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
show.url = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondolaId: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondolaId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondolaId: args.gondolaId,
    }

    return show.definition.url
            .replace('{gondolaId}', parsedArgs.gondolaId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
show.get = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
show.head = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
const showForm = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
showForm.get = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
showForm.head = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const GondolaShareController = { show }

export default GondolaShareController