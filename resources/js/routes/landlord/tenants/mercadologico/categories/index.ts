import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
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

const categories = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    restore: Object.assign(restore, restore),
}

export default categories