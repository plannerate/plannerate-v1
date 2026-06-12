import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::edit
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:35
* @route '//plannerate.localhost/tenants/{tenant}/access'
*/
export const edit = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/access',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::edit
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:35
* @route '//plannerate.localhost/tenants/{tenant}/access'
*/
edit.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::edit
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:35
* @route '//plannerate.localhost/tenants/{tenant}/access'
*/
edit.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::edit
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:35
* @route '//plannerate.localhost/tenants/{tenant}/access'
*/
edit.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::edit
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:35
* @route '//plannerate.localhost/tenants/{tenant}/access'
*/
const editForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::edit
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:35
* @route '//plannerate.localhost/tenants/{tenant}/access'
*/
editForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::edit
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:35
* @route '//plannerate.localhost/tenants/{tenant}/access'
*/
editForm.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::store
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/access/users'
*/
export const store = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/access/users',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::store
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/access/users'
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
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::store
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/access/users'
*/
store.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::store
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/access/users'
*/
const storeForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::store
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/access/users'
*/
storeForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::update
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:176
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
export const update = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '//plannerate.localhost/tenants/{tenant}/access/users/{userId}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::update
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:176
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
update.url = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            userId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        userId: args.userId,
    }

    return update.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{userId}', parsedArgs.userId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::update
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:176
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
update.put = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::update
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:176
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
const updateForm = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::update
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:176
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
updateForm.put = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::toggleActive
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:227
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/toggle-active'
*/
export const toggleActive = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleActive.url(args, options),
    method: 'patch',
})

toggleActive.definition = {
    methods: ["patch"],
    url: '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/toggle-active',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::toggleActive
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:227
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/toggle-active'
*/
toggleActive.url = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            userId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        userId: args.userId,
    }

    return toggleActive.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{userId}', parsedArgs.userId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::toggleActive
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:227
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/toggle-active'
*/
toggleActive.patch = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleActive.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::toggleActive
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:227
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/toggle-active'
*/
const toggleActiveForm = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: toggleActive.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::toggleActive
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:227
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/toggle-active'
*/
toggleActiveForm.patch = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: toggleActive.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

toggleActive.form = toggleActiveForm

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::syncRoles
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:253
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/sync-roles'
*/
export const syncRoles = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: syncRoles.url(args, options),
    method: 'patch',
})

syncRoles.definition = {
    methods: ["patch"],
    url: '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/sync-roles',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::syncRoles
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:253
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/sync-roles'
*/
syncRoles.url = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            userId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        userId: args.userId,
    }

    return syncRoles.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{userId}', parsedArgs.userId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::syncRoles
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:253
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/sync-roles'
*/
syncRoles.patch = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: syncRoles.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::syncRoles
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:253
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/sync-roles'
*/
const syncRolesForm = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncRoles.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::syncRoles
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:253
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/sync-roles'
*/
syncRolesForm.patch = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncRoles.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

syncRoles.form = syncRolesForm

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::destroy
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:286
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
export const destroy = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/tenants/{tenant}/access/users/{userId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::destroy
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:286
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
destroy.url = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            userId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        userId: args.userId,
    }

    return destroy.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{userId}', parsedArgs.userId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::destroy
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:286
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
destroy.delete = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::destroy
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:286
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
const destroyForm = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::destroy
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:286
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}'
*/
destroyForm.delete = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::restore
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:306
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/restore'
*/
export const restore = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: restore.url(args, options),
    method: 'patch',
})

restore.definition = {
    methods: ["patch"],
    url: '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/restore',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::restore
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:306
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/restore'
*/
restore.url = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            userId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        userId: args.userId,
    }

    return restore.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{userId}', parsedArgs.userId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::restore
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:306
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/restore'
*/
restore.patch = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: restore.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::restore
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:306
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/restore'
*/
const restoreForm = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::restore
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:306
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/restore'
*/
restoreForm.patch = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

restore.form = restoreForm

const TenantUserAccessController = { edit, store, update, toggleActive, syncRoles, destroy, restore }

export default TenantUserAccessController