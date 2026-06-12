import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import users from './users'
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

const access = {
    edit: Object.assign(edit, edit),
    users: Object.assign(users, users),
}

export default access