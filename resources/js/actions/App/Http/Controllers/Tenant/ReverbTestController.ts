import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::index
* @see app/Http/Controllers/Tenant/ReverbTestController.php:15
* @route '/reverb-test'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/reverb-test',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::index
* @see app/Http/Controllers/Tenant/ReverbTestController.php:15
* @route '/reverb-test'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::index
* @see app/Http/Controllers/Tenant/ReverbTestController.php:15
* @route '/reverb-test'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::index
* @see app/Http/Controllers/Tenant/ReverbTestController.php:15
* @route '/reverb-test'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::index
* @see app/Http/Controllers/Tenant/ReverbTestController.php:15
* @route '/reverb-test'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::index
* @see app/Http/Controllers/Tenant/ReverbTestController.php:15
* @route '/reverb-test'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::index
* @see app/Http/Controllers/Tenant/ReverbTestController.php:15
* @route '/reverb-test'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::notify
* @see app/Http/Controllers/Tenant/ReverbTestController.php:22
* @route '/reverb-test/notify'
*/
export const notify = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: notify.url(options),
    method: 'post',
})

notify.definition = {
    methods: ["post"],
    url: '/reverb-test/notify',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::notify
* @see app/Http/Controllers/Tenant/ReverbTestController.php:22
* @route '/reverb-test/notify'
*/
notify.url = (options?: RouteQueryOptions) => {
    return notify.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::notify
* @see app/Http/Controllers/Tenant/ReverbTestController.php:22
* @route '/reverb-test/notify'
*/
notify.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: notify.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::notify
* @see app/Http/Controllers/Tenant/ReverbTestController.php:22
* @route '/reverb-test/notify'
*/
const notifyForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: notify.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ReverbTestController::notify
* @see app/Http/Controllers/Tenant/ReverbTestController.php:22
* @route '/reverb-test/notify'
*/
notifyForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: notify.url(options),
    method: 'post',
})

notify.form = notifyForm

const ReverbTestController = { index, notify }

export default ReverbTestController