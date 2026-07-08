import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
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

const categories = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    restore: Object.assign(restore, restore),
}

export default categories