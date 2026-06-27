import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import sales from './sales'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::executionFeedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
export const executionFeedback = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: executionFeedback.url(args, options),
    method: 'get',
})

executionFeedback.definition = {
    methods: ["get","head"],
    url: '/api/plannerate/products/{product}/execution-feedback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::executionFeedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
executionFeedback.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return executionFeedback.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::executionFeedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
executionFeedback.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: executionFeedback.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::executionFeedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
executionFeedback.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: executionFeedback.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::executionFeedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
const executionFeedbackForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: executionFeedback.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::executionFeedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
executionFeedbackForm.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: executionFeedback.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductExecutionController::executionFeedback
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductExecutionController.php:26
* @route '/api/plannerate/products/{product}/execution-feedback'
*/
executionFeedbackForm.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: executionFeedback.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

executionFeedback.form = executionFeedbackForm

const products = {
    sales: Object.assign(sales, sales),
    executionFeedback: Object.assign(executionFeedback, executionFeedback),
}

export default products