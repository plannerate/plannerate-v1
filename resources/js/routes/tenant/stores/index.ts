import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\StoreController::index
* @see app/Http/Controllers/Tenant/StoreController.php:32
* @route '/stores'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/stores',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\StoreController::index
* @see app/Http/Controllers/Tenant/StoreController.php:32
* @route '/stores'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\StoreController::index
* @see app/Http/Controllers/Tenant/StoreController.php:32
* @route '/stores'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::index
* @see app/Http/Controllers/Tenant/StoreController.php:32
* @route '/stores'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::index
* @see app/Http/Controllers/Tenant/StoreController.php:32
* @route '/stores'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::index
* @see app/Http/Controllers/Tenant/StoreController.php:32
* @route '/stores'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::index
* @see app/Http/Controllers/Tenant/StoreController.php:32
* @route '/stores'
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
* @see \App\Http\Controllers\Tenant\StoreController::create
* @see app/Http/Controllers/Tenant/StoreController.php:85
* @route '/stores/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/stores/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\StoreController::create
* @see app/Http/Controllers/Tenant/StoreController.php:85
* @route '/stores/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\StoreController::create
* @see app/Http/Controllers/Tenant/StoreController.php:85
* @route '/stores/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::create
* @see app/Http/Controllers/Tenant/StoreController.php:85
* @route '/stores/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::create
* @see app/Http/Controllers/Tenant/StoreController.php:85
* @route '/stores/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::create
* @see app/Http/Controllers/Tenant/StoreController.php:85
* @route '/stores/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::create
* @see app/Http/Controllers/Tenant/StoreController.php:85
* @route '/stores/create'
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
* @see \App\Http\Controllers\Tenant\StoreController::store
* @see app/Http/Controllers/Tenant/StoreController.php:95
* @route '/stores'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/stores',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\StoreController::store
* @see app/Http/Controllers/Tenant/StoreController.php:95
* @route '/stores'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\StoreController::store
* @see app/Http/Controllers/Tenant/StoreController.php:95
* @route '/stores'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::store
* @see app/Http/Controllers/Tenant/StoreController.php:95
* @route '/stores'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::store
* @see app/Http/Controllers/Tenant/StoreController.php:95
* @route '/stores'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\StoreController::edit
* @see app/Http/Controllers/Tenant/StoreController.php:117
* @route '/stores/{store}/edit'
*/
export const edit = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/stores/{store}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\StoreController::edit
* @see app/Http/Controllers/Tenant/StoreController.php:117
* @route '/stores/{store}/edit'
*/
edit.url = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { store: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { store: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            store: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        store: typeof args.store === 'object'
        ? args.store.id
        : args.store,
    }

    return edit.definition.url
            .replace('{store}', parsedArgs.store.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\StoreController::edit
* @see app/Http/Controllers/Tenant/StoreController.php:117
* @route '/stores/{store}/edit'
*/
edit.get = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::edit
* @see app/Http/Controllers/Tenant/StoreController.php:117
* @route '/stores/{store}/edit'
*/
edit.head = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::edit
* @see app/Http/Controllers/Tenant/StoreController.php:117
* @route '/stores/{store}/edit'
*/
const editForm = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::edit
* @see app/Http/Controllers/Tenant/StoreController.php:117
* @route '/stores/{store}/edit'
*/
editForm.get = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::edit
* @see app/Http/Controllers/Tenant/StoreController.php:117
* @route '/stores/{store}/edit'
*/
editForm.head = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\StoreController::update
* @see app/Http/Controllers/Tenant/StoreController.php:140
* @route '/stores/{store}'
*/
export const update = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/stores/{store}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\StoreController::update
* @see app/Http/Controllers/Tenant/StoreController.php:140
* @route '/stores/{store}'
*/
update.url = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { store: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { store: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            store: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        store: typeof args.store === 'object'
        ? args.store.id
        : args.store,
    }

    return update.definition.url
            .replace('{store}', parsedArgs.store.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\StoreController::update
* @see app/Http/Controllers/Tenant/StoreController.php:140
* @route '/stores/{store}'
*/
update.put = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::update
* @see app/Http/Controllers/Tenant/StoreController.php:140
* @route '/stores/{store}'
*/
update.patch = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::update
* @see app/Http/Controllers/Tenant/StoreController.php:140
* @route '/stores/{store}'
*/
const updateForm = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::update
* @see app/Http/Controllers/Tenant/StoreController.php:140
* @route '/stores/{store}'
*/
updateForm.put = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::update
* @see app/Http/Controllers/Tenant/StoreController.php:140
* @route '/stores/{store}'
*/
updateForm.patch = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\StoreController::destroy
* @see app/Http/Controllers/Tenant/StoreController.php:277
* @route '/stores/{store}'
*/
export const destroy = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/stores/{store}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\StoreController::destroy
* @see app/Http/Controllers/Tenant/StoreController.php:277
* @route '/stores/{store}'
*/
destroy.url = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { store: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { store: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            store: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        store: typeof args.store === 'object'
        ? args.store.id
        : args.store,
    }

    return destroy.definition.url
            .replace('{store}', parsedArgs.store.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\StoreController::destroy
* @see app/Http/Controllers/Tenant/StoreController.php:277
* @route '/stores/{store}'
*/
destroy.delete = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::destroy
* @see app/Http/Controllers/Tenant/StoreController.php:277
* @route '/stores/{store}'
*/
const destroyForm = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\StoreController::destroy
* @see app/Http/Controllers/Tenant/StoreController.php:277
* @route '/stores/{store}'
*/
destroyForm.delete = (args: { store: string | { id: string } } | [store: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const stores = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default stores