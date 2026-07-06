import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::resend
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:403
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/password-setup/resend'
*/
export const resend = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/password-setup/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::resend
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:403
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/password-setup/resend'
*/
resend.url = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions) => {
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

    return resend.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{userId}', parsedArgs.userId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::resend
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:403
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/password-setup/resend'
*/
resend.post = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::resend
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:403
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/password-setup/resend'
*/
const resendForm = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resend.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantUserAccessController::resend
* @see app/Http/Controllers/Landlord/TenantUserAccessController.php:403
* @route '//plannerate.localhost/tenants/{tenant}/access/users/{userId}/password-setup/resend'
*/
resendForm.post = (args: { tenant: string | { id: string }, userId: string | number } | [tenant: string | { id: string }, userId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resend.url(args, options),
    method: 'post',
})

resend.form = resendForm

const passwordSetup = {
    resend: Object.assign(resend, resend),
}

export default passwordSetup