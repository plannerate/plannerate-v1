import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::index
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:33
* @route '/dimensions'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dimensions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::index
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:33
* @route '/dimensions'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::index
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:33
* @route '/dimensions'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::index
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:33
* @route '/dimensions'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::index
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:33
* @route '/dimensions'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::index
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:33
* @route '/dimensions'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::index
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:33
* @route '/dimensions'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReferencePage
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:116
* @route '/dimensions/sync-from-reference-page'
*/
export const syncFromReferencePage = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncFromReferencePage.url(options),
    method: 'post',
})

syncFromReferencePage.definition = {
    methods: ["post"],
    url: '/dimensions/sync-from-reference-page',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReferencePage
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:116
* @route '/dimensions/sync-from-reference-page'
*/
syncFromReferencePage.url = (options?: RouteQueryOptions) => {
    return syncFromReferencePage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReferencePage
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:116
* @route '/dimensions/sync-from-reference-page'
*/
syncFromReferencePage.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncFromReferencePage.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReferencePage
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:116
* @route '/dimensions/sync-from-reference-page'
*/
const syncFromReferencePageForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncFromReferencePage.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReferencePage
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:116
* @route '/dimensions/sync-from-reference-page'
*/
syncFromReferencePageForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncFromReferencePage.url(options),
    method: 'post',
})

syncFromReferencePage.form = syncFromReferencePageForm

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReference
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:76
* @route '/dimensions/{product}/sync-from-reference'
*/
export const syncFromReference = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncFromReference.url(args, options),
    method: 'post',
})

syncFromReference.definition = {
    methods: ["post"],
    url: '/dimensions/{product}/sync-from-reference',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReference
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:76
* @route '/dimensions/{product}/sync-from-reference'
*/
syncFromReference.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return syncFromReference.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReference
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:76
* @route '/dimensions/{product}/sync-from-reference'
*/
syncFromReference.post = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncFromReference.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReference
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:76
* @route '/dimensions/{product}/sync-from-reference'
*/
const syncFromReferenceForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncFromReference.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::syncFromReference
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:76
* @route '/dimensions/{product}/sync-from-reference'
*/
syncFromReferenceForm.post = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncFromReference.url(args, options),
    method: 'post',
})

syncFromReference.form = syncFromReferenceForm

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::update
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:61
* @route '/dimensions/{product}'
*/
export const update = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/dimensions/{product}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::update
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:61
* @route '/dimensions/{product}'
*/
update.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::update
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:61
* @route '/dimensions/{product}'
*/
update.patch = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::update
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:61
* @route '/dimensions/{product}'
*/
const updateForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductDimensionController::update
* @see app/Http/Controllers/Tenant/ProductDimensionController.php:61
* @route '/dimensions/{product}'
*/
updateForm.patch = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

const dimensions = {
    index: Object.assign(index, index),
    syncFromReferencePage: Object.assign(syncFromReferencePage, syncFromReferencePage),
    syncFromReference: Object.assign(syncFromReference, syncFromReference),
    update: Object.assign(update, update),
}

export default dimensions