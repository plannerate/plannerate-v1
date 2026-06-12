import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::update
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:15
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
*/
export const update = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '//plannerate.localhost/tenants/{tenant}/socialite-provider',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::update
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:15
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
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
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::update
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:15
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
*/
update.put = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::update
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:15
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
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
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::update
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:15
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
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
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::destroy
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
*/
export const destroy = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/tenants/{tenant}/socialite-provider',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::destroy
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
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
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::destroy
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
*/
destroy.delete = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::destroy
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
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
* @see \App\Http\Controllers\Landlord\TenantSocialiteProviderController::destroy
* @see app/Http/Controllers/Landlord/TenantSocialiteProviderController.php:47
* @route '//plannerate.localhost/tenants/{tenant}/socialite-provider'
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

const TenantSocialiteProviderController = { update, destroy }

export default TenantSocialiteProviderController