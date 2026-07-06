import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::edit
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:20
* @route '/password/setup/{code}'
*/
export const edit = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/password/setup/{code}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::edit
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:20
* @route '/password/setup/{code}'
*/
edit.url = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{code}', parsedArgs.code.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::edit
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:20
* @route '/password/setup/{code}'
*/
edit.get = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::edit
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:20
* @route '/password/setup/{code}'
*/
edit.head = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::edit
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:20
* @route '/password/setup/{code}'
*/
const editForm = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::edit
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:20
* @route '/password/setup/{code}'
*/
editForm.get = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::edit
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:20
* @route '/password/setup/{code}'
*/
editForm.head = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\PasswordSetupController::update
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:46
* @route '/password/setup/{code}'
*/
export const update = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/password/setup/{code}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::update
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:46
* @route '/password/setup/{code}'
*/
update.url = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{code}', parsedArgs.code.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::update
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:46
* @route '/password/setup/{code}'
*/
update.post = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::update
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:46
* @route '/password/setup/{code}'
*/
const updateForm = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PasswordSetupController::update
* @see app/Http/Controllers/Tenant/PasswordSetupController.php:46
* @route '/password/setup/{code}'
*/
updateForm.post = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, options),
    method: 'post',
})

update.form = updateForm

const passwordSetup = {
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
}

export default passwordSetup