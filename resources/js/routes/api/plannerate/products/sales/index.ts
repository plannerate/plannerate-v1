import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController::summary
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php:25
* @route '/api/plannerate/products/{product}/sales/summary'
*/
export const summary = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: summary.url(args, options),
    method: 'get',
})

summary.definition = {
    methods: ["get","head"],
    url: '/api/plannerate/products/{product}/sales/summary',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController::summary
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php:25
* @route '/api/plannerate/products/{product}/sales/summary'
*/
summary.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    if (Array.isArray(args)) {
        args = {
            product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        product: args.product,
    }

    return summary.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController::summary
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php:25
* @route '/api/plannerate/products/{product}/sales/summary'
*/
summary.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: summary.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController::summary
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php:25
* @route '/api/plannerate/products/{product}/sales/summary'
*/
summary.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: summary.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController::summary
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php:25
* @route '/api/plannerate/products/{product}/sales/summary'
*/
const summaryForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: summary.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController::summary
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php:25
* @route '/api/plannerate/products/{product}/sales/summary'
*/
summaryForm.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: summary.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController::summary
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php:25
* @route '/api/plannerate/products/{product}/sales/summary'
*/
summaryForm.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: summary.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

summary.form = summaryForm

const sales = {
    summary: Object.assign(summary, summary),
}

export default sales