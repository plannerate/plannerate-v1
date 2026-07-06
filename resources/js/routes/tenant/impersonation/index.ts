import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::consume
* @see app/Http/Controllers/Tenant/ImpersonationController.php:22
* @route '/impersonation/consume/{code}'
*/
export const consume = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: consume.url(args, options),
    method: 'get',
})

consume.definition = {
    methods: ["get","head"],
    url: '/impersonation/consume/{code}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::consume
* @see app/Http/Controllers/Tenant/ImpersonationController.php:22
* @route '/impersonation/consume/{code}'
*/
consume.url = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { code: args }
    }

    if (Array.isArray(args)) {
        args = {
            code: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        code: args.code,
    }

    return consume.definition.url
            .replace('{code}', parsedArgs.code.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::consume
* @see app/Http/Controllers/Tenant/ImpersonationController.php:22
* @route '/impersonation/consume/{code}'
*/
consume.get = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: consume.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::consume
* @see app/Http/Controllers/Tenant/ImpersonationController.php:22
* @route '/impersonation/consume/{code}'
*/
consume.head = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: consume.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::consume
* @see app/Http/Controllers/Tenant/ImpersonationController.php:22
* @route '/impersonation/consume/{code}'
*/
const consumeForm = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: consume.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::consume
* @see app/Http/Controllers/Tenant/ImpersonationController.php:22
* @route '/impersonation/consume/{code}'
*/
consumeForm.get = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: consume.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::consume
* @see app/Http/Controllers/Tenant/ImpersonationController.php:22
* @route '/impersonation/consume/{code}'
*/
consumeForm.head = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: consume.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

consume.form = consumeForm

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::leave
* @see app/Http/Controllers/Tenant/ImpersonationController.php:46
* @route '/impersonation/leave'
*/
export const leave = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: leave.url(options),
    method: 'post',
})

leave.definition = {
    methods: ["post"],
    url: '/impersonation/leave',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::leave
* @see app/Http/Controllers/Tenant/ImpersonationController.php:46
* @route '/impersonation/leave'
*/
leave.url = (options?: RouteQueryOptions) => {
    return leave.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::leave
* @see app/Http/Controllers/Tenant/ImpersonationController.php:46
* @route '/impersonation/leave'
*/
leave.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: leave.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::leave
* @see app/Http/Controllers/Tenant/ImpersonationController.php:46
* @route '/impersonation/leave'
*/
const leaveForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: leave.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ImpersonationController::leave
* @see app/Http/Controllers/Tenant/ImpersonationController.php:46
* @route '/impersonation/leave'
*/
leaveForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: leave.url(options),
    method: 'post',
})

leave.form = leaveForm

const impersonation = {
    consume: Object.assign(consume, consume),
    leave: Object.assign(leave, leave),
}

export default impersonation