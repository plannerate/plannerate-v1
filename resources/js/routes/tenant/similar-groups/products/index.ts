import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::search
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/similar-groups/products/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::search
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::search
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::search
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::search
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
const searchForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::search
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
searchForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::search
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
searchForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: search.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

search.form = searchForm

const products = {
    search: Object.assign(search, search),
}

export default products