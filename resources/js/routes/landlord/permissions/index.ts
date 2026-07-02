import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\PermissionController::sync
* @see app/Http/Controllers/Landlord/PermissionController.php:180
* @route '//plannerate.localhost/permissions/sync'
*/
export const sync = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(options),
    method: 'post',
})

sync.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/permissions/sync',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\PermissionController::sync
* @see app/Http/Controllers/Landlord/PermissionController.php:180
* @route '//plannerate.localhost/permissions/sync'
*/
sync.url = (options?: RouteQueryOptions) => {
    return sync.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PermissionController::sync
* @see app/Http/Controllers/Landlord/PermissionController.php:180
* @route '//plannerate.localhost/permissions/sync'
*/
sync.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::sync
* @see app/Http/Controllers/Landlord/PermissionController.php:180
* @route '//plannerate.localhost/permissions/sync'
*/
const syncForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sync.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::sync
* @see app/Http/Controllers/Landlord/PermissionController.php:180
* @route '//plannerate.localhost/permissions/sync'
*/
syncForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sync.url(options),
    method: 'post',
})

sync.form = syncForm

/**
* @see \App\Http\Controllers\Landlord\PermissionController::index
* @see app/Http/Controllers/Landlord/PermissionController.php:37
* @route '//plannerate.localhost/permissions'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/permissions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\PermissionController::index
* @see app/Http/Controllers/Landlord/PermissionController.php:37
* @route '//plannerate.localhost/permissions'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PermissionController::index
* @see app/Http/Controllers/Landlord/PermissionController.php:37
* @route '//plannerate.localhost/permissions'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::index
* @see app/Http/Controllers/Landlord/PermissionController.php:37
* @route '//plannerate.localhost/permissions'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::index
* @see app/Http/Controllers/Landlord/PermissionController.php:37
* @route '//plannerate.localhost/permissions'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::index
* @see app/Http/Controllers/Landlord/PermissionController.php:37
* @route '//plannerate.localhost/permissions'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::index
* @see app/Http/Controllers/Landlord/PermissionController.php:37
* @route '//plannerate.localhost/permissions'
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
* @see \App\Http\Controllers\Landlord\PermissionController::create
* @see app/Http/Controllers/Landlord/PermissionController.php:89
* @route '//plannerate.localhost/permissions/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/permissions/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\PermissionController::create
* @see app/Http/Controllers/Landlord/PermissionController.php:89
* @route '//plannerate.localhost/permissions/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PermissionController::create
* @see app/Http/Controllers/Landlord/PermissionController.php:89
* @route '//plannerate.localhost/permissions/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::create
* @see app/Http/Controllers/Landlord/PermissionController.php:89
* @route '//plannerate.localhost/permissions/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::create
* @see app/Http/Controllers/Landlord/PermissionController.php:89
* @route '//plannerate.localhost/permissions/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::create
* @see app/Http/Controllers/Landlord/PermissionController.php:89
* @route '//plannerate.localhost/permissions/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::create
* @see app/Http/Controllers/Landlord/PermissionController.php:89
* @route '//plannerate.localhost/permissions/create'
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
* @see \App\Http\Controllers\Landlord\PermissionController::store
* @see app/Http/Controllers/Landlord/PermissionController.php:102
* @route '//plannerate.localhost/permissions'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/permissions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\PermissionController::store
* @see app/Http/Controllers/Landlord/PermissionController.php:102
* @route '//plannerate.localhost/permissions'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PermissionController::store
* @see app/Http/Controllers/Landlord/PermissionController.php:102
* @route '//plannerate.localhost/permissions'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::store
* @see app/Http/Controllers/Landlord/PermissionController.php:102
* @route '//plannerate.localhost/permissions'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::store
* @see app/Http/Controllers/Landlord/PermissionController.php:102
* @route '//plannerate.localhost/permissions'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\PermissionController::edit
* @see app/Http/Controllers/Landlord/PermissionController.php:127
* @route '//plannerate.localhost/permissions/{permission}/edit'
*/
export const edit = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/permissions/{permission}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\PermissionController::edit
* @see app/Http/Controllers/Landlord/PermissionController.php:127
* @route '//plannerate.localhost/permissions/{permission}/edit'
*/
edit.url = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { permission: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { permission: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            permission: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        permission: typeof args.permission === 'object'
        ? args.permission.id
        : args.permission,
    }

    return edit.definition.url
            .replace('{permission}', parsedArgs.permission.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PermissionController::edit
* @see app/Http/Controllers/Landlord/PermissionController.php:127
* @route '//plannerate.localhost/permissions/{permission}/edit'
*/
edit.get = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::edit
* @see app/Http/Controllers/Landlord/PermissionController.php:127
* @route '//plannerate.localhost/permissions/{permission}/edit'
*/
edit.head = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::edit
* @see app/Http/Controllers/Landlord/PermissionController.php:127
* @route '//plannerate.localhost/permissions/{permission}/edit'
*/
const editForm = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::edit
* @see app/Http/Controllers/Landlord/PermissionController.php:127
* @route '//plannerate.localhost/permissions/{permission}/edit'
*/
editForm.get = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::edit
* @see app/Http/Controllers/Landlord/PermissionController.php:127
* @route '//plannerate.localhost/permissions/{permission}/edit'
*/
editForm.head = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\PermissionController::update
* @see app/Http/Controllers/Landlord/PermissionController.php:147
* @route '//plannerate.localhost/permissions/{permission}'
*/
export const update = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '//plannerate.localhost/permissions/{permission}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Landlord\PermissionController::update
* @see app/Http/Controllers/Landlord/PermissionController.php:147
* @route '//plannerate.localhost/permissions/{permission}'
*/
update.url = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { permission: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { permission: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            permission: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        permission: typeof args.permission === 'object'
        ? args.permission.id
        : args.permission,
    }

    return update.definition.url
            .replace('{permission}', parsedArgs.permission.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PermissionController::update
* @see app/Http/Controllers/Landlord/PermissionController.php:147
* @route '//plannerate.localhost/permissions/{permission}'
*/
update.put = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::update
* @see app/Http/Controllers/Landlord/PermissionController.php:147
* @route '//plannerate.localhost/permissions/{permission}'
*/
update.patch = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::update
* @see app/Http/Controllers/Landlord/PermissionController.php:147
* @route '//plannerate.localhost/permissions/{permission}'
*/
const updateForm = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::update
* @see app/Http/Controllers/Landlord/PermissionController.php:147
* @route '//plannerate.localhost/permissions/{permission}'
*/
updateForm.put = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::update
* @see app/Http/Controllers/Landlord/PermissionController.php:147
* @route '//plannerate.localhost/permissions/{permission}'
*/
updateForm.patch = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\PermissionController::destroy
* @see app/Http/Controllers/Landlord/PermissionController.php:258
* @route '//plannerate.localhost/permissions/{permission}'
*/
export const destroy = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/permissions/{permission}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\PermissionController::destroy
* @see app/Http/Controllers/Landlord/PermissionController.php:258
* @route '//plannerate.localhost/permissions/{permission}'
*/
destroy.url = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { permission: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { permission: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            permission: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        permission: typeof args.permission === 'object'
        ? args.permission.id
        : args.permission,
    }

    return destroy.definition.url
            .replace('{permission}', parsedArgs.permission.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PermissionController::destroy
* @see app/Http/Controllers/Landlord/PermissionController.php:258
* @route '//plannerate.localhost/permissions/{permission}'
*/
destroy.delete = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::destroy
* @see app/Http/Controllers/Landlord/PermissionController.php:258
* @route '//plannerate.localhost/permissions/{permission}'
*/
const destroyForm = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PermissionController::destroy
* @see app/Http/Controllers/Landlord/PermissionController.php:258
* @route '//plannerate.localhost/permissions/{permission}'
*/
destroyForm.delete = (args: { permission: string | { id: string } } | [permission: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const permissions = {
    sync: Object.assign(sync, sync),
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default permissions