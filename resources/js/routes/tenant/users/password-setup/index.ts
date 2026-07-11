import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\UserController::resend
* @see app/Http/Controllers/Tenant/UserController.php:191
* @route '/users/{user}/password-setup/resend'
*/
export const resend = (args: { user: string | { id: string } } | [user: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/users/{user}/password-setup/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\UserController::resend
* @see app/Http/Controllers/Tenant/UserController.php:191
* @route '/users/{user}/password-setup/resend'
*/
resend.url = (args: { user: string | { id: string } } | [user: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        user: typeof args.user === 'object'
        ? args.user.id
        : args.user,
    }

    return resend.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\UserController::resend
* @see app/Http/Controllers/Tenant/UserController.php:191
* @route '/users/{user}/password-setup/resend'
*/
resend.post = (args: { user: string | { id: string } } | [user: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\UserController::resend
* @see app/Http/Controllers/Tenant/UserController.php:191
* @route '/users/{user}/password-setup/resend'
*/
const resendForm = (args: { user: string | { id: string } } | [user: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resend.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\UserController::resend
* @see app/Http/Controllers/Tenant/UserController.php:191
* @route '/users/{user}/password-setup/resend'
*/
resendForm.post = (args: { user: string | { id: string } } | [user: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resend.url(args, options),
    method: 'post',
})

resend.form = resendForm

const passwordSetup = {
    resend: Object.assign(resend, resend),
}

export default passwordSetup