import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ProductController::index
* @see app/Http/Controllers/Tenant/ProductController.php:57
* @route '/products'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::index
* @see app/Http/Controllers/Tenant/ProductController.php:57
* @route '/products'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::index
* @see app/Http/Controllers/Tenant/ProductController.php:57
* @route '/products'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::index
* @see app/Http/Controllers/Tenant/ProductController.php:57
* @route '/products'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::index
* @see app/Http/Controllers/Tenant/ProductController.php:57
* @route '/products'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::index
* @see app/Http/Controllers/Tenant/ProductController.php:57
* @route '/products'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::index
* @see app/Http/Controllers/Tenant/ProductController.php:57
* @route '/products'
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
* @see \App\Http\Controllers\Tenant\ProductController::create
* @see app/Http/Controllers/Tenant/ProductController.php:288
* @route '/products/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/products/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::create
* @see app/Http/Controllers/Tenant/ProductController.php:288
* @route '/products/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::create
* @see app/Http/Controllers/Tenant/ProductController.php:288
* @route '/products/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::create
* @see app/Http/Controllers/Tenant/ProductController.php:288
* @route '/products/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::create
* @see app/Http/Controllers/Tenant/ProductController.php:288
* @route '/products/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::create
* @see app/Http/Controllers/Tenant/ProductController.php:288
* @route '/products/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::create
* @see app/Http/Controllers/Tenant/ProductController.php:288
* @route '/products/create'
*/
createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\Tenant\ProductController::store
* @see app/Http/Controllers/Tenant/ProductController.php:298
* @route '/products'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/products',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::store
* @see app/Http/Controllers/Tenant/ProductController.php:298
* @route '/products'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::store
* @see app/Http/Controllers/Tenant/ProductController.php:298
* @route '/products'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::store
* @see app/Http/Controllers/Tenant/ProductController.php:298
* @route '/products'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::store
* @see app/Http/Controllers/Tenant/ProductController.php:298
* @route '/products'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\ProductController::edit
* @see app/Http/Controllers/Tenant/ProductController.php:341
* @route '/products/{product}/edit'
*/
export const edit = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/products/{product}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::edit
* @see app/Http/Controllers/Tenant/ProductController.php:341
* @route '/products/{product}/edit'
*/
edit.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::edit
* @see app/Http/Controllers/Tenant/ProductController.php:341
* @route '/products/{product}/edit'
*/
edit.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::edit
* @see app/Http/Controllers/Tenant/ProductController.php:341
* @route '/products/{product}/edit'
*/
edit.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::edit
* @see app/Http/Controllers/Tenant/ProductController.php:341
* @route '/products/{product}/edit'
*/
const editForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::edit
* @see app/Http/Controllers/Tenant/ProductController.php:341
* @route '/products/{product}/edit'
*/
editForm.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::edit
* @see app/Http/Controllers/Tenant/ProductController.php:341
* @route '/products/{product}/edit'
*/
editForm.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Tenant\ProductController::update
* @see app/Http/Controllers/Tenant/ProductController.php:352
* @route '/products/{product}'
*/
export const update = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/products/{product}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::update
* @see app/Http/Controllers/Tenant/ProductController.php:352
* @route '/products/{product}'
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
* @see \App\Http\Controllers\Tenant\ProductController::update
* @see app/Http/Controllers/Tenant/ProductController.php:352
* @route '/products/{product}'
*/
update.put = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::update
* @see app/Http/Controllers/Tenant/ProductController.php:352
* @route '/products/{product}'
*/
update.patch = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::update
* @see app/Http/Controllers/Tenant/ProductController.php:352
* @route '/products/{product}'
*/
const updateForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::update
* @see app/Http/Controllers/Tenant/ProductController.php:352
* @route '/products/{product}'
*/
updateForm.put = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::update
* @see app/Http/Controllers/Tenant/ProductController.php:352
* @route '/products/{product}'
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

/**
* @see \App\Http\Controllers\Tenant\ProductController::destroy
* @see app/Http/Controllers/Tenant/ProductController.php:393
* @route '/products/{product}'
*/
export const destroy = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/products/{product}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::destroy
* @see app/Http/Controllers/Tenant/ProductController.php:393
* @route '/products/{product}'
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
* @see \App\Http\Controllers\Tenant\ProductController::destroy
* @see app/Http/Controllers/Tenant/ProductController.php:393
* @route '/products/{product}'
*/
destroy.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::destroy
* @see app/Http/Controllers/Tenant/ProductController.php:393
* @route '/products/{product}'
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
* @see \App\Http\Controllers\Tenant\ProductController::destroy
* @see app/Http/Controllers/Tenant/ProductController.php:393
* @route '/products/{product}'
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
* @see \App\Http\Controllers\Tenant\ProductController::sales
* @see app/Http/Controllers/Tenant/ProductController.php:206
* @route '/products/{product}/sales'
*/
export const sales = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sales.url(args, options),
    method: 'get',
})

sales.definition = {
    methods: ["get","head"],
    url: '/products/{product}/sales',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::sales
* @see app/Http/Controllers/Tenant/ProductController.php:206
* @route '/products/{product}/sales'
*/
sales.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return sales.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::sales
* @see app/Http/Controllers/Tenant/ProductController.php:206
* @route '/products/{product}/sales'
*/
sales.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sales.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sales
* @see app/Http/Controllers/Tenant/ProductController.php:206
* @route '/products/{product}/sales'
*/
sales.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sales.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sales
* @see app/Http/Controllers/Tenant/ProductController.php:206
* @route '/products/{product}/sales'
*/
const salesForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sales.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sales
* @see app/Http/Controllers/Tenant/ProductController.php:206
* @route '/products/{product}/sales'
*/
salesForm.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sales.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sales
* @see app/Http/Controllers/Tenant/ProductController.php:206
* @route '/products/{product}/sales'
*/
salesForm.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sales.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

sales.form = salesForm

/**
* @see \App\Http\Controllers\Tenant\ProductController::sortimentAttributes
* @see app/Http/Controllers/Tenant/ProductController.php:96
* @route '/products/sortiment-attributes'
*/
export const sortimentAttributes = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sortimentAttributes.url(options),
    method: 'get',
})

sortimentAttributes.definition = {
    methods: ["get","head"],
    url: '/products/sortiment-attributes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::sortimentAttributes
* @see app/Http/Controllers/Tenant/ProductController.php:96
* @route '/products/sortiment-attributes'
*/
sortimentAttributes.url = (options?: RouteQueryOptions) => {
    return sortimentAttributes.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::sortimentAttributes
* @see app/Http/Controllers/Tenant/ProductController.php:96
* @route '/products/sortiment-attributes'
*/
sortimentAttributes.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sortimentAttributes.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sortimentAttributes
* @see app/Http/Controllers/Tenant/ProductController.php:96
* @route '/products/sortiment-attributes'
*/
sortimentAttributes.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sortimentAttributes.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sortimentAttributes
* @see app/Http/Controllers/Tenant/ProductController.php:96
* @route '/products/sortiment-attributes'
*/
const sortimentAttributesForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sortimentAttributes.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sortimentAttributes
* @see app/Http/Controllers/Tenant/ProductController.php:96
* @route '/products/sortiment-attributes'
*/
sortimentAttributesForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sortimentAttributes.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::sortimentAttributes
* @see app/Http/Controllers/Tenant/ProductController.php:96
* @route '/products/sortiment-attributes'
*/
sortimentAttributesForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sortimentAttributes.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

sortimentAttributes.form = sortimentAttributesForm

/**
* @see \App\Http\Controllers\Tenant\ProductController::syncSingle
* @see app/Http/Controllers/Tenant/ProductController.php:38
* @route '/products/sync-single'
*/
export const syncSingle = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncSingle.url(options),
    method: 'post',
})

syncSingle.definition = {
    methods: ["post"],
    url: '/products/sync-single',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::syncSingle
* @see app/Http/Controllers/Tenant/ProductController.php:38
* @route '/products/sync-single'
*/
syncSingle.url = (options?: RouteQueryOptions) => {
    return syncSingle.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::syncSingle
* @see app/Http/Controllers/Tenant/ProductController.php:38
* @route '/products/sync-single'
*/
syncSingle.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncSingle.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::syncSingle
* @see app/Http/Controllers/Tenant/ProductController.php:38
* @route '/products/sync-single'
*/
const syncSingleForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncSingle.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::syncSingle
* @see app/Http/Controllers/Tenant/ProductController.php:38
* @route '/products/sync-single'
*/
syncSingleForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncSingle.url(options),
    method: 'post',
})

syncSingle.form = syncSingleForm

/**
* @see \App\Http\Controllers\Tenant\ProductController::updateImages
* @see app/Http/Controllers/Tenant/ProductController.php:34
* @route '/products/update-images'
*/
export const updateImages = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateImages.url(options),
    method: 'post',
})

updateImages.definition = {
    methods: ["post"],
    url: '/products/update-images',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProductController::updateImages
* @see app/Http/Controllers/Tenant/ProductController.php:34
* @route '/products/update-images'
*/
updateImages.url = (options?: RouteQueryOptions) => {
    return updateImages.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProductController::updateImages
* @see app/Http/Controllers/Tenant/ProductController.php:34
* @route '/products/update-images'
*/
updateImages.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateImages.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::updateImages
* @see app/Http/Controllers/Tenant/ProductController.php:34
* @route '/products/update-images'
*/
const updateImagesForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateImages.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProductController::updateImages
* @see app/Http/Controllers/Tenant/ProductController.php:34
* @route '/products/update-images'
*/
updateImagesForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateImages.url(options),
    method: 'post',
})

updateImages.form = updateImagesForm

const ProductController = { index, create, store, edit, update, destroy, sales, sortimentAttributes, syncSingle, updateImages }

export default ProductController