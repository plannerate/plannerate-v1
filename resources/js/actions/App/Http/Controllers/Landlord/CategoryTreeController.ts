import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::index
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:38
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico'
*/
export const index = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::index
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:38
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico'
*/
index.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return index.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::index
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:38
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico'
*/
index.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::index
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:38
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico'
*/
index.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::index
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:38
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico'
*/
const indexForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::index
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:38
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico'
*/
indexForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::index
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:38
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico'
*/
indexForm.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::children
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:58
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/children'
*/
export const children = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(args, options),
    method: 'get',
})

children.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/children',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::children
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:58
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/children'
*/
children.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return children.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::children
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:58
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/children'
*/
children.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::children
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:58
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/children'
*/
children.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: children.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::children
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:58
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/children'
*/
const childrenForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::children
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:58
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/children'
*/
childrenForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::children
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:58
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/children'
*/
childrenForm.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

children.form = childrenForm

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::products
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:75
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products'
*/
export const products = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

products.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::products
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:75
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products'
*/
products.url = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            category: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        category: args.category,
    }

    return products.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::products
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:75
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products'
*/
products.get = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::products
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:75
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products'
*/
products.head = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: products.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::products
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:75
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products'
*/
const productsForm = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::products
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:75
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products'
*/
productsForm.get = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::products
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:75
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/products'
*/
productsForm.head = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\CategoryTreeController::move
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:121
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/move'
*/
export const move = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(args, options),
    method: 'post',
})

move.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/move',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::move
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:121
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/move'
*/
move.url = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            category: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        category: args.category,
    }

    return move.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::move
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:121
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/move'
*/
move.post = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::move
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:121
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/move'
*/
const moveForm = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: move.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::move
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:121
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/{category}/move'
*/
moveForm.post = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: move.url(args, options),
    method: 'post',
})

move.form = moveForm

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::moveProducts
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:203
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/move-products'
*/
export const moveProducts = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: moveProducts.url(args, options),
    method: 'post',
})

moveProducts.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/move-products',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::moveProducts
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:203
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/move-products'
*/
moveProducts.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return moveProducts.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::moveProducts
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:203
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/move-products'
*/
moveProducts.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: moveProducts.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::moveProducts
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:203
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/move-products'
*/
const moveProductsForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: moveProducts.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::moveProducts
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:203
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/move-products'
*/
moveProductsForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: moveProducts.url(args, options),
    method: 'post',
})

moveProducts.form = moveProductsForm

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::store
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:143
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories'
*/
export const store = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/categories',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::store
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:143
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories'
*/
store.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return store.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::store
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:143
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories'
*/
store.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::store
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:143
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories'
*/
const storeForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::store
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:143
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories'
*/
storeForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::update
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:159
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
export const update = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::update
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:159
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
update.url = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            category: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        category: args.category,
    }

    return update.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::update
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:159
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
update.put = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::update
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:159
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
const updateForm = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::update
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:159
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
updateForm.put = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\CategoryTreeController::destroy
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:175
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
export const destroy = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::destroy
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:175
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
destroy.url = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            category: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        category: args.category,
    }

    return destroy.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::destroy
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:175
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
destroy.delete = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::destroy
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:175
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
const destroyForm = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::destroy
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:175
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}'
*/
destroyForm.delete = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\CategoryTreeController::restore
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:189
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}/restore'
*/
export const restore = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::restore
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:189
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}/restore'
*/
restore.url = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            category: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        category: args.category,
    }

    return restore.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::restore
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:189
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}/restore'
*/
restore.post = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::restore
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:189
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}/restore'
*/
const restoreForm = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\CategoryTreeController::restore
* @see app/Http/Controllers/Landlord/CategoryTreeController.php:189
* @route '//plannerate.localhost/tenants/{tenant}/mercadologico/categories/{category}/restore'
*/
restoreForm.post = (args: { tenant: string | { id: string }, category: string | number } | [tenant: string | { id: string }, category: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, options),
    method: 'post',
})

restore.form = restoreForm

const CategoryTreeController = { index, children, products, move, moveProducts, store, update, destroy, restore }

export default CategoryTreeController