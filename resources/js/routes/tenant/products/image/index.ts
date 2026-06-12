import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import ai from './ai'
import repository from './repository'
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

const image = {
    upload: Object.assign(upload, upload),
    destroy: Object.assign(destroy, destroy),
    ai: Object.assign(ai, ai),
    repository: Object.assign(repository, repository),
}

export default image