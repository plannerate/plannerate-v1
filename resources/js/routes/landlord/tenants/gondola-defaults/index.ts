import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::edit
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
*/
export const edit = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/gondola-defaults',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::edit
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
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
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::edit
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
*/
edit.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::edit
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
*/
edit.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::edit
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
*/
const editForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::edit
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
*/
editForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::edit
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
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
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::update
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:70
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
*/
export const update = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '//plannerate.localhost/tenants/{tenant}/gondola-defaults',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::update
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:70
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
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
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::update
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:70
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
*/
update.put = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::update
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:70
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
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
* @see \App\Http\Controllers\Landlord\TenantGondolaDefaultsController::update
* @see app/Http/Controllers/Landlord/TenantGondolaDefaultsController.php:70
* @route '//plannerate.localhost/tenants/{tenant}/gondola-defaults'
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

const gondolaDefaults = {
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
}

export default gondolaDefaults