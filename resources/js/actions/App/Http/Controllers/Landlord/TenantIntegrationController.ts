import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::edit
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:20
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
export const edit = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/integration',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::edit
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:20
* @route '//plannerate.localhost/tenants/{tenant}/integration'
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
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::edit
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:20
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
edit.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::edit
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:20
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
edit.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::edit
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:20
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
const editForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::edit
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:20
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
editForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::edit
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:20
* @route '//plannerate.localhost/tenants/{tenant}/integration'
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
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::update
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:84
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
export const update = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '//plannerate.localhost/tenants/{tenant}/integration',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::update
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:84
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
update.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::update
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:84
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
update.put = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::update
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:84
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
const updateForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::update
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:84
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
updateForm.put = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::testConnection
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:167
* @route '//plannerate.localhost/tenants/{tenant}/integration/test-connection'
*/
export const testConnection = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testConnection.url(args, options),
    method: 'post',
})

testConnection.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/integration/test-connection',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::testConnection
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:167
* @route '//plannerate.localhost/tenants/{tenant}/integration/test-connection'
*/
testConnection.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return testConnection.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::testConnection
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:167
* @route '//plannerate.localhost/tenants/{tenant}/integration/test-connection'
*/
testConnection.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testConnection.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::testConnection
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:167
* @route '//plannerate.localhost/tenants/{tenant}/integration/test-connection'
*/
const testConnectionForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: testConnection.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::testConnection
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:167
* @route '//plannerate.localhost/tenants/{tenant}/integration/test-connection'
*/
testConnectionForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: testConnection.url(args, options),
    method: 'post',
})

testConnection.form = testConnectionForm

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::toggleStatus
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/integration/toggle-status'
*/
export const toggleStatus = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleStatus.url(args, options),
    method: 'patch',
})

toggleStatus.definition = {
    methods: ["patch"],
    url: '//plannerate.localhost/tenants/{tenant}/integration/toggle-status',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::toggleStatus
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/integration/toggle-status'
*/
toggleStatus.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return toggleStatus.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::toggleStatus
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/integration/toggle-status'
*/
toggleStatus.patch = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleStatus.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::toggleStatus
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/integration/toggle-status'
*/
const toggleStatusForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: toggleStatus.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::toggleStatus
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:140
* @route '//plannerate.localhost/tenants/{tenant}/integration/toggle-status'
*/
toggleStatusForm.patch = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: toggleStatus.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

toggleStatus.form = toggleStatusForm

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::destroy
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:126
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
export const destroy = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/tenants/{tenant}/integration',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::destroy
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:126
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
destroy.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::destroy
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:126
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
destroy.delete = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::destroy
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:126
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
const destroyForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantIntegrationController::destroy
* @see app/Http/Controllers/Landlord/TenantIntegrationController.php:126
* @route '//plannerate.localhost/tenants/{tenant}/integration'
*/
destroyForm.delete = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const TenantIntegrationController = { edit, update, testConnection, toggleStatus, destroy }

export default TenantIntegrationController