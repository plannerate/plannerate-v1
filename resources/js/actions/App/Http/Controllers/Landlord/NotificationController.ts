import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\NotificationController::markAllRead
* @see app/Http/Controllers/Landlord/NotificationController.php:26
* @route '//plannerate.localhost/notifications/read-all'
*/
export const markAllRead = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markAllRead.url(options),
    method: 'post',
})

markAllRead.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/notifications/read-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markAllRead
* @see app/Http/Controllers/Landlord/NotificationController.php:26
* @route '//plannerate.localhost/notifications/read-all'
*/
markAllRead.url = (options?: RouteQueryOptions) => {
    return markAllRead.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markAllRead
* @see app/Http/Controllers/Landlord/NotificationController.php:26
* @route '//plannerate.localhost/notifications/read-all'
*/
markAllRead.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markAllRead.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markAllRead
* @see app/Http/Controllers/Landlord/NotificationController.php:26
* @route '//plannerate.localhost/notifications/read-all'
*/
const markAllReadForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: markAllRead.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markAllRead
* @see app/Http/Controllers/Landlord/NotificationController.php:26
* @route '//plannerate.localhost/notifications/read-all'
*/
markAllReadForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: markAllRead.url(options),
    method: 'post',
})

markAllRead.form = markAllReadForm

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroyAll
* @see app/Http/Controllers/Landlord/NotificationController.php:35
* @route '//plannerate.localhost/notifications'
*/
export const destroyAll = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyAll.url(options),
    method: 'delete',
})

destroyAll.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/notifications',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroyAll
* @see app/Http/Controllers/Landlord/NotificationController.php:35
* @route '//plannerate.localhost/notifications'
*/
destroyAll.url = (options?: RouteQueryOptions) => {
    return destroyAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroyAll
* @see app/Http/Controllers/Landlord/NotificationController.php:35
* @route '//plannerate.localhost/notifications'
*/
destroyAll.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyAll.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroyAll
* @see app/Http/Controllers/Landlord/NotificationController.php:35
* @route '//plannerate.localhost/notifications'
*/
const destroyAllForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroyAll.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroyAll
* @see app/Http/Controllers/Landlord/NotificationController.php:35
* @route '//plannerate.localhost/notifications'
*/
destroyAllForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroyAll.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroyAll.form = destroyAllForm

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markRead
* @see app/Http/Controllers/Landlord/NotificationController.php:14
* @route '//plannerate.localhost/notifications/{id}/read'
*/
export const markRead = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markRead.url(args, options),
    method: 'patch',
})

markRead.definition = {
    methods: ["patch"],
    url: '//plannerate.localhost/notifications/{id}/read',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markRead
* @see app/Http/Controllers/Landlord/NotificationController.php:14
* @route '//plannerate.localhost/notifications/{id}/read'
*/
markRead.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return markRead.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markRead
* @see app/Http/Controllers/Landlord/NotificationController.php:14
* @route '//plannerate.localhost/notifications/{id}/read'
*/
markRead.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markRead.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markRead
* @see app/Http/Controllers/Landlord/NotificationController.php:14
* @route '//plannerate.localhost/notifications/{id}/read'
*/
const markReadForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: markRead.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::markRead
* @see app/Http/Controllers/Landlord/NotificationController.php:14
* @route '//plannerate.localhost/notifications/{id}/read'
*/
markReadForm.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: markRead.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

markRead.form = markReadForm

/**
* @see \App\Http\Controllers\Landlord\NotificationController::download
* @see app/Http/Controllers/Landlord/NotificationController.php:55
* @route '//plannerate.localhost/notifications/{id}/download'
*/
export const download = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/notifications/{id}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\NotificationController::download
* @see app/Http/Controllers/Landlord/NotificationController.php:55
* @route '//plannerate.localhost/notifications/{id}/download'
*/
download.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return download.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\NotificationController::download
* @see app/Http/Controllers/Landlord/NotificationController.php:55
* @route '//plannerate.localhost/notifications/{id}/download'
*/
download.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::download
* @see app/Http/Controllers/Landlord/NotificationController.php:55
* @route '//plannerate.localhost/notifications/{id}/download'
*/
download.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::download
* @see app/Http/Controllers/Landlord/NotificationController.php:55
* @route '//plannerate.localhost/notifications/{id}/download'
*/
const downloadForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::download
* @see app/Http/Controllers/Landlord/NotificationController.php:55
* @route '//plannerate.localhost/notifications/{id}/download'
*/
downloadForm.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::download
* @see app/Http/Controllers/Landlord/NotificationController.php:55
* @route '//plannerate.localhost/notifications/{id}/download'
*/
downloadForm.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

download.form = downloadForm

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroy
* @see app/Http/Controllers/Landlord/NotificationController.php:44
* @route '//plannerate.localhost/notifications/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/notifications/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroy
* @see app/Http/Controllers/Landlord/NotificationController.php:44
* @route '//plannerate.localhost/notifications/{id}'
*/
destroy.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return destroy.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroy
* @see app/Http/Controllers/Landlord/NotificationController.php:44
* @route '//plannerate.localhost/notifications/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroy
* @see app/Http/Controllers/Landlord/NotificationController.php:44
* @route '//plannerate.localhost/notifications/{id}'
*/
const destroyForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\NotificationController::destroy
* @see app/Http/Controllers/Landlord/NotificationController.php:44
* @route '//plannerate.localhost/notifications/{id}'
*/
destroyForm.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const NotificationController = { markAllRead, destroyAll, markRead, download, destroy }

export default NotificationController