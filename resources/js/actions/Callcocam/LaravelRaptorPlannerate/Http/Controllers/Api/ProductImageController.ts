import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/api/products/update-image',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
updateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(options),
    method: 'post',
})

update.form = updateForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::uploadImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:46
* @route '/api/products/{product}/upload-image'
*/
export const uploadImage = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadImage.url(args, options),
    method: 'post',
})

uploadImage.definition = {
    methods: ["post"],
    url: '/api/products/{product}/upload-image',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::uploadImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:46
* @route '/api/products/{product}/upload-image'
*/
uploadImage.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return uploadImage.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::uploadImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:46
* @route '/api/products/{product}/upload-image'
*/
uploadImage.post = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadImage.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::uploadImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:46
* @route '/api/products/{product}/upload-image'
*/
const uploadImageForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: uploadImage.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::uploadImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:46
* @route '/api/products/{product}/upload-image'
*/
uploadImageForm.post = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: uploadImage.url(args, options),
    method: 'post',
})

uploadImage.form = uploadImageForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::deleteImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:106
* @route '/api/products/{product}/delete-image'
*/
export const deleteImage = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteImage.url(args, options),
    method: 'delete',
})

deleteImage.definition = {
    methods: ["delete"],
    url: '/api/products/{product}/delete-image',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::deleteImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:106
* @route '/api/products/{product}/delete-image'
*/
deleteImage.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return deleteImage.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::deleteImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:106
* @route '/api/products/{product}/delete-image'
*/
deleteImage.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteImage.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::deleteImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:106
* @route '/api/products/{product}/delete-image'
*/
const deleteImageForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: deleteImage.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::deleteImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:106
* @route '/api/products/{product}/delete-image'
*/
deleteImageForm.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: deleteImage.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

deleteImage.form = deleteImageForm

const ProductImageController = { update, uploadImage, deleteImage }

export default ProductImageController