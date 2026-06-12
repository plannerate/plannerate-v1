import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::store
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:17
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
*/
export const store = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/cloudflare',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::store
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:17
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
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
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::store
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:17
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
*/
store.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::store
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:17
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
*/
const storeForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::store
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:17
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
*/
storeForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::destroy
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:63
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
*/
export const destroy = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/tenants/{tenant}/cloudflare',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::destroy
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:63
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
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
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::destroy
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:63
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
*/
destroy.delete = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::destroy
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:63
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
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
* @see \App\Http\Controllers\Landlord\TenantCloudflareController::destroy
* @see app/Http/Controllers/Landlord/TenantCloudflareController.php:63
* @route '//plannerate.localhost/tenants/{tenant}/cloudflare'
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

const TenantCloudflareController = { store, destroy }

export default TenantCloudflareController