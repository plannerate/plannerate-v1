import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ProductImageController::process
* @see app/Http/Controllers/Tenant/ProductImageController.php:94
* @route '/products/image/ai/process'
*/
export const process = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: process.url(options),
    method: 'post',
})

process.definition = {
    methods: ["post"],
    url: '/products/image/ai/process',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::process
* @see app/Http/Controllers/Tenant/ProductImageController.php:94
* @route '/products/image/ai/process'
*/
process.url = (options?: RouteQueryOptions) => {
    return process.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::process
* @see app/Http/Controllers/Tenant/ProductImageController.php:94
* @route '/products/image/ai/process'
*/
process.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: process.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::process
* @see app/Http/Controllers/Tenant/ProductImageController.php:94
* @route '/products/image/ai/process'
*/
const processForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: process.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::process
* @see app/Http/Controllers/Tenant/ProductImageController.php:94
* @route '/products/image/ai/process'
*/
processForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: process.url(options),
    method: 'post',
})

process.form = processForm

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::status
* @see app/Http/Controllers/Tenant/ProductImageController.php:128
* @route '/products/image/ai/operations/{operation}'
*/
export const status = (args: { operation: string | number } | [operation: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(args, options),
    method: 'get',
})

status.definition = {
    methods: ["get","head"],
    url: '/products/image/ai/operations/{operation}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::status
* @see app/Http/Controllers/Tenant/ProductImageController.php:128
* @route '/products/image/ai/operations/{operation}'
*/
status.url = (args: { operation: string | number } | [operation: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { operation: args }
    }

    if (Array.isArray(args)) {
        args = {
            operation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        operation: args.operation,
    }

    return status.definition.url
            .replace('{operation}', parsedArgs.operation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::status
* @see app/Http/Controllers/Tenant/ProductImageController.php:128
* @route '/products/image/ai/operations/{operation}'
*/
status.get = (args: { operation: string | number } | [operation: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::status
* @see app/Http/Controllers/Tenant/ProductImageController.php:128
* @route '/products/image/ai/operations/{operation}'
*/
status.head = (args: { operation: string | number } | [operation: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: status.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::status
* @see app/Http/Controllers/Tenant/ProductImageController.php:128
* @route '/products/image/ai/operations/{operation}'
*/
const statusForm = (args: { operation: string | number } | [operation: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: status.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::status
* @see app/Http/Controllers/Tenant/ProductImageController.php:128
* @route '/products/image/ai/operations/{operation}'
*/
statusForm.get = (args: { operation: string | number } | [operation: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: status.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::status
* @see app/Http/Controllers/Tenant/ProductImageController.php:128
* @route '/products/image/ai/operations/{operation}'
*/
statusForm.head = (args: { operation: string | number } | [operation: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: status.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

status.form = statusForm

const ai = {
    process: Object.assign(process, process),
    status: Object.assign(status, status),
}

export default ai