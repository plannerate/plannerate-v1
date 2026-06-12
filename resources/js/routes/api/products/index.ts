import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::details
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
export const details = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: details.url(args, options),
    method: 'get',
})

details.definition = {
    methods: ["get","head"],
    url: '/api/products/details/{ean}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::details
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
details.url = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return details.definition.url
            .replace('{ean}', parsedArgs.ean.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::details
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
details.get = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: details.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::details
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
details.head = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: details.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::details
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
const detailsForm = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: details.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::details
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
detailsForm.get = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: details.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController::details
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductDetailsController.php:18
* @route '/api/products/details/{ean}'
*/
detailsForm.head = (args: { ean: string | number } | [ean: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: details.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

details.form = detailsForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::updateImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
export const updateImage = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateImage.url(options),
    method: 'post',
})

updateImage.definition = {
    methods: ["post"],
    url: '/api/products/update-image',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::updateImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
updateImage.url = (options?: RouteQueryOptions) => {
    return updateImage.definition.url + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::updateImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
updateImage.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateImage.url(options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::updateImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
const updateImageForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateImage.url(options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController::updateImage
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Api/ProductImageController.php:24
* @route '/api/products/update-image'
*/
updateImageForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateImage.url(options),
    method: 'post',
})

updateImage.form = updateImageForm

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

const products = {
    details: Object.assign(details, details),
    updateImage: Object.assign(updateImage, updateImage),
    uploadImage: Object.assign(uploadImage, uploadImage),
    deleteImage: Object.assign(deleteImage, deleteImage),
}

export default products