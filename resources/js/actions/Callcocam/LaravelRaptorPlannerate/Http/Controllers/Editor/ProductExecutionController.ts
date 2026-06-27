import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::feedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
export const feedback = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: feedback.url(args, options),
    method: 'get',
})

feedback.definition = {
    methods: ["get","head"],
    url: '/api/plannerate/products/{product}/execution-feedback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::feedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
feedback.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return feedback.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::feedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
feedback.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: feedback.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::feedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
feedback.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: feedback.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::feedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
const feedbackForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: feedback.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::feedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
feedbackForm.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: feedback.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::feedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
feedbackForm.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: feedback.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

feedback.form = feedbackForm

const ProductExecutionController = { feedback }

export default ProductExecutionController