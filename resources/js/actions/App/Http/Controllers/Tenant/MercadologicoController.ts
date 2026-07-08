import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::index
* @see app/Http/Controllers/Tenant/MercadologicoController.php:38
* @route '/mercadologico'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/mercadologico',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::index
* @see app/Http/Controllers/Tenant/MercadologicoController.php:38
* @route '/mercadologico'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::index
* @see app/Http/Controllers/Tenant/MercadologicoController.php:38
* @route '/mercadologico'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::index
* @see app/Http/Controllers/Tenant/MercadologicoController.php:38
* @route '/mercadologico'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::index
* @see app/Http/Controllers/Tenant/MercadologicoController.php:38
* @route '/mercadologico'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::index
* @see app/Http/Controllers/Tenant/MercadologicoController.php:38
* @route '/mercadologico'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::index
* @see app/Http/Controllers/Tenant/MercadologicoController.php:38
* @route '/mercadologico'
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
* @see \App\Http\Controllers\Tenant\MercadologicoController::children
* @see app/Http/Controllers/Tenant/MercadologicoController.php:51
* @route '/mercadologico/children'
*/
export const children = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(options),
    method: 'get',
})

children.definition = {
    methods: ["get","head"],
    url: '/mercadologico/children',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::children
* @see app/Http/Controllers/Tenant/MercadologicoController.php:51
* @route '/mercadologico/children'
*/
children.url = (options?: RouteQueryOptions) => {
    return children.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::children
* @see app/Http/Controllers/Tenant/MercadologicoController.php:51
* @route '/mercadologico/children'
*/
children.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::children
* @see app/Http/Controllers/Tenant/MercadologicoController.php:51
* @route '/mercadologico/children'
*/
children.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: children.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::children
* @see app/Http/Controllers/Tenant/MercadologicoController.php:51
* @route '/mercadologico/children'
*/
const childrenForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::children
* @see app/Http/Controllers/Tenant/MercadologicoController.php:51
* @route '/mercadologico/children'
*/
childrenForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::children
* @see app/Http/Controllers/Tenant/MercadologicoController.php:51
* @route '/mercadologico/children'
*/
childrenForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

children.form = childrenForm

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::products
* @see app/Http/Controllers/Tenant/MercadologicoController.php:65
* @route '/mercadologico/{category}/products'
*/
export const products = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

products.definition = {
    methods: ["get","head"],
    url: '/mercadologico/{category}/products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::products
* @see app/Http/Controllers/Tenant/MercadologicoController.php:65
* @route '/mercadologico/{category}/products'
*/
products.url = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: args.category,
    }

    return products.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::products
* @see app/Http/Controllers/Tenant/MercadologicoController.php:65
* @route '/mercadologico/{category}/products'
*/
products.get = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::products
* @see app/Http/Controllers/Tenant/MercadologicoController.php:65
* @route '/mercadologico/{category}/products'
*/
products.head = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: products.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::products
* @see app/Http/Controllers/Tenant/MercadologicoController.php:65
* @route '/mercadologico/{category}/products'
*/
const productsForm = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::products
* @see app/Http/Controllers/Tenant/MercadologicoController.php:65
* @route '/mercadologico/{category}/products'
*/
productsForm.get = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::products
* @see app/Http/Controllers/Tenant/MercadologicoController.php:65
* @route '/mercadologico/{category}/products'
*/
productsForm.head = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

products.form = productsForm

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::move
* @see app/Http/Controllers/Tenant/MercadologicoController.php:107
* @route '/mercadologico/{category}/move'
*/
export const move = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(args, options),
    method: 'post',
})

move.definition = {
    methods: ["post"],
    url: '/mercadologico/{category}/move',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::move
* @see app/Http/Controllers/Tenant/MercadologicoController.php:107
* @route '/mercadologico/{category}/move'
*/
move.url = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: args.category,
    }

    return move.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::move
* @see app/Http/Controllers/Tenant/MercadologicoController.php:107
* @route '/mercadologico/{category}/move'
*/
move.post = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::move
* @see app/Http/Controllers/Tenant/MercadologicoController.php:107
* @route '/mercadologico/{category}/move'
*/
const moveForm = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: move.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::move
* @see app/Http/Controllers/Tenant/MercadologicoController.php:107
* @route '/mercadologico/{category}/move'
*/
moveForm.post = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: move.url(args, options),
    method: 'post',
})

move.form = moveForm

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::moveProducts
* @see app/Http/Controllers/Tenant/MercadologicoController.php:184
* @route '/mercadologico/move-products'
*/
export const moveProducts = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: moveProducts.url(options),
    method: 'post',
})

moveProducts.definition = {
    methods: ["post"],
    url: '/mercadologico/move-products',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::moveProducts
* @see app/Http/Controllers/Tenant/MercadologicoController.php:184
* @route '/mercadologico/move-products'
*/
moveProducts.url = (options?: RouteQueryOptions) => {
    return moveProducts.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::moveProducts
* @see app/Http/Controllers/Tenant/MercadologicoController.php:184
* @route '/mercadologico/move-products'
*/
moveProducts.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: moveProducts.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::moveProducts
* @see app/Http/Controllers/Tenant/MercadologicoController.php:184
* @route '/mercadologico/move-products'
*/
const moveProductsForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: moveProducts.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::moveProducts
* @see app/Http/Controllers/Tenant/MercadologicoController.php:184
* @route '/mercadologico/move-products'
*/
moveProductsForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: moveProducts.url(options),
    method: 'post',
})

moveProducts.form = moveProductsForm

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::store
* @see app/Http/Controllers/Tenant/MercadologicoController.php:128
* @route '/mercadologico/categories'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/mercadologico/categories',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::store
* @see app/Http/Controllers/Tenant/MercadologicoController.php:128
* @route '/mercadologico/categories'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::store
* @see app/Http/Controllers/Tenant/MercadologicoController.php:128
* @route '/mercadologico/categories'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::store
* @see app/Http/Controllers/Tenant/MercadologicoController.php:128
* @route '/mercadologico/categories'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::store
* @see app/Http/Controllers/Tenant/MercadologicoController.php:128
* @route '/mercadologico/categories'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::update
* @see app/Http/Controllers/Tenant/MercadologicoController.php:144
* @route '/mercadologico/categories/{category}'
*/
export const update = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/mercadologico/categories/{category}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::update
* @see app/Http/Controllers/Tenant/MercadologicoController.php:144
* @route '/mercadologico/categories/{category}'
*/
update.url = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: args.category,
    }

    return update.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::update
* @see app/Http/Controllers/Tenant/MercadologicoController.php:144
* @route '/mercadologico/categories/{category}'
*/
update.put = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::update
* @see app/Http/Controllers/Tenant/MercadologicoController.php:144
* @route '/mercadologico/categories/{category}'
*/
const updateForm = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::update
* @see app/Http/Controllers/Tenant/MercadologicoController.php:144
* @route '/mercadologico/categories/{category}'
*/
updateForm.put = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::destroy
* @see app/Http/Controllers/Tenant/MercadologicoController.php:159
* @route '/mercadologico/categories/{category}'
*/
export const destroy = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/mercadologico/categories/{category}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::destroy
* @see app/Http/Controllers/Tenant/MercadologicoController.php:159
* @route '/mercadologico/categories/{category}'
*/
destroy.url = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: args.category,
    }

    return destroy.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::destroy
* @see app/Http/Controllers/Tenant/MercadologicoController.php:159
* @route '/mercadologico/categories/{category}'
*/
destroy.delete = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::destroy
* @see app/Http/Controllers/Tenant/MercadologicoController.php:159
* @route '/mercadologico/categories/{category}'
*/
const destroyForm = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::destroy
* @see app/Http/Controllers/Tenant/MercadologicoController.php:159
* @route '/mercadologico/categories/{category}'
*/
destroyForm.delete = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\MercadologicoController::restore
* @see app/Http/Controllers/Tenant/MercadologicoController.php:172
* @route '/mercadologico/categories/{category}/restore'
*/
export const restore = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '/mercadologico/categories/{category}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::restore
* @see app/Http/Controllers/Tenant/MercadologicoController.php:172
* @route '/mercadologico/categories/{category}/restore'
*/
restore.url = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: args.category,
    }

    return restore.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::restore
* @see app/Http/Controllers/Tenant/MercadologicoController.php:172
* @route '/mercadologico/categories/{category}/restore'
*/
restore.post = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::restore
* @see app/Http/Controllers/Tenant/MercadologicoController.php:172
* @route '/mercadologico/categories/{category}/restore'
*/
const restoreForm = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\MercadologicoController::restore
* @see app/Http/Controllers/Tenant/MercadologicoController.php:172
* @route '/mercadologico/categories/{category}/restore'
*/
restoreForm.post = (args: { category: string | number } | [category: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, options),
    method: 'post',
})

restore.form = restoreForm

const MercadologicoController = { index, children, products, move, moveProducts, store, update, destroy, restore }

export default MercadologicoController