import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ProductImageController::upload
* @see app/Http/Controllers/Tenant/ProductImageController.php:30
* @route '/products/image/upload'
*/
export const upload = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(options),
    method: 'post',
})

upload.definition = {
    methods: ["post"],
    url: '/products/image/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::upload
* @see app/Http/Controllers/Tenant/ProductImageController.php:30
* @route '/products/image/upload'
*/
upload.url = (options?: RouteQueryOptions) => {
    return upload.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::upload
* @see app/Http/Controllers/Tenant/ProductImageController.php:30
* @route '/products/image/upload'
*/
upload.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::upload
* @see app/Http/Controllers/Tenant/ProductImageController.php:30
* @route '/products/image/upload'
*/
const uploadForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upload.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::upload
* @see app/Http/Controllers/Tenant/ProductImageController.php:30
* @route '/products/image/upload'
*/
uploadForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upload.url(options),
    method: 'post',
})

upload.form = uploadForm

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::destroy
* @see app/Http/Controllers/Tenant/ProductImageController.php:68
* @route '/products/image/{product}'
*/
export const destroy = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/products/image/{product}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::destroy
* @see app/Http/Controllers/Tenant/ProductImageController.php:68
* @route '/products/image/{product}'
*/
destroy.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::destroy
* @see app/Http/Controllers/Tenant/ProductImageController.php:68
* @route '/products/image/{product}'
*/
destroy.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::destroy
* @see app/Http/Controllers/Tenant/ProductImageController.php:68
* @route '/products/image/{product}'
*/
const destroyForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::destroy
* @see app/Http/Controllers/Tenant/ProductImageController.php:68
* @route '/products/image/{product}'
*/
destroyForm.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

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

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetchFromRepository
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
export const fetchFromRepository = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchFromRepository.url(options),
    method: 'post',
})

fetchFromRepository.definition = {
    methods: ["post"],
    url: '/products/image/repository/fetch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetchFromRepository
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
fetchFromRepository.url = (options?: RouteQueryOptions) => {
    return fetchFromRepository.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetchFromRepository
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
fetchFromRepository.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchFromRepository.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetchFromRepository
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
const fetchFromRepositoryForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: fetchFromRepository.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductImageController::fetchFromRepository
* @see app/Http/Controllers/Tenant/ProductImageController.php:165
* @route '/products/image/repository/fetch'
*/
fetchFromRepositoryForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: fetchFromRepository.url(options),
    method: 'post',
})

fetchFromRepository.form = fetchFromRepositoryForm

const ProductImageController = { upload, destroy, process, status, fetchFromRepository }

export default ProductImageController