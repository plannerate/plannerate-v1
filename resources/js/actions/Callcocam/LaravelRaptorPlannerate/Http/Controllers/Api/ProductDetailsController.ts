import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
export const show = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/products/details/{ean}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
show.url = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ean: args }
    }

    if (Array.isArray(args)) {
        args = {
            ean: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ean: args.ean,
    }

    return show.definition.url
            .replace('{ean}', parsedArgs.ean.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
show.get = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
show.head = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
const showForm = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
showForm.get = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
showForm.head = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const ProductDetailsController = { show }

export default ProductDetailsController