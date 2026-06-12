import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::share
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
export const share = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: share.url(args, options),
    method: 'get',
})

share.definition = {
    methods: ["get","head"],
    url: '/gondola/{gondolaId}/share',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::share
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
share.url = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return share.definition.url
            .replace('{gondolaId}', parsedArgs.gondolaId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::share
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
share.get = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: share.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::share
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
share.head = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: share.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::share
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
const shareForm = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: share.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::share
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
shareForm.get = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: share.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController::share
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaShareController.php:19
* @route '/gondola/{gondolaId}/share'
*/
shareForm.head = (args: { gondolaId: string | number } | [gondolaId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: share.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

share.form = shareForm

const gondola = {
    share: Object.assign(share, share),
}

export default gondola