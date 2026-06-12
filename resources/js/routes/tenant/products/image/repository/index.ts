import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetch
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
export const fetch = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetch.url(options),
    method: 'post',
})

fetch.definition = {
    methods: ["post"],
    url: '/products/image/repository/fetch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetch
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
fetch.url = (options?: RouteQueryOptions) => {
    return fetch.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetch
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
fetch.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetch.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetch
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
const fetchForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: fetch.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetch
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
fetchForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: fetch.url(options),
    method: 'post',
})

fetch.form = fetchForm

const repository = {
    fetch: Object.assign(fetch, fetch),
}

export default repository