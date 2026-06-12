import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\NotificationController::readAll
* @see app/Http/Controllers/Tenant/NotificationController.php:28
* @route '/notifications/read-all'
*/
export const readAll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

readAll.definition = {
    methods: ["post"],
    url: '/notifications/read-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\NotificationController::readAll
* @see app/Http/Controllers/Tenant/NotificationController.php:28
* @route '/notifications/read-all'
*/
readAll.url = (options?: RouteQueryOptions) => {
    return readAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\NotificationController::readAll
* @see app/Http/Controllers/Tenant/NotificationController.php:28
* @route '/notifications/read-all'
*/
readAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::readAll
* @see app/Http/Controllers/Tenant/NotificationController.php:28
* @route '/notifications/read-all'
*/
const readAllForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: readAll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::readAll
* @see app/Http/Controllers/Tenant/NotificationController.php:28
* @route '/notifications/read-all'
*/
readAllForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: readAll.url(options),
    method: 'post',
})

readAll.form = readAllForm

/**
* @see \App\Http\Controllers\Tenant\NotificationController::destroyAll
* @see app/Http/Controllers/Tenant/NotificationController.php:38
* @route '/notifications'
*/
export const destroyAll = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyAll.url(options),
    method: 'delete',
})

destroyAll.definition = {
    methods: ["delete"],
    url: '/notifications',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\NotificationController::destroyAll
* @see app/Http/Controllers/Tenant/NotificationController.php:38
* @route '/notifications'
*/
destroyAll.url = (options?: RouteQueryOptions) => {
    return destroyAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\NotificationController::destroyAll
* @see app/Http/Controllers/Tenant/NotificationController.php:38
* @route '/notifications'
*/
destroyAll.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyAll.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::destroyAll
* @see app/Http/Controllers/Tenant/NotificationController.php:38
* @route '/notifications'
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
* @see \App\Http\Controllers\Tenant\NotificationController::destroyAll
* @see app/Http/Controllers/Tenant/NotificationController.php:38
* @route '/notifications'
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
* @see \App\Http\Controllers\Tenant\NotificationController::read
* @see app/Http/Controllers/Tenant/NotificationController.php:15
* @route '/notifications/{id}/read'
*/
export const read = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: read.url(args, options),
    method: 'patch',
})

read.definition = {
    methods: ["patch"],
    url: '/notifications/{id}/read',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\NotificationController::read
* @see app/Http/Controllers/Tenant/NotificationController.php:15
* @route '/notifications/{id}/read'
*/
read.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return read.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\NotificationController::read
* @see app/Http/Controllers/Tenant/NotificationController.php:15
* @route '/notifications/{id}/read'
*/
read.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: read.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::read
* @see app/Http/Controllers/Tenant/NotificationController.php:15
* @route '/notifications/{id}/read'
*/
const readForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: read.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::read
* @see app/Http/Controllers/Tenant/NotificationController.php:15
* @route '/notifications/{id}/read'
*/
readForm.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: read.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

read.form = readForm

/**
* @see \App\Http\Controllers\Tenant\NotificationController::download
* @see app/Http/Controllers/Tenant/NotificationController.php:61
* @route '/notifications/{id}/download'
*/
export const download = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/notifications/{id}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\NotificationController::download
* @see app/Http/Controllers/Tenant/NotificationController.php:61
* @route '/notifications/{id}/download'
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
* @see \App\Http\Controllers\Tenant\NotificationController::download
* @see app/Http/Controllers/Tenant/NotificationController.php:61
* @route '/notifications/{id}/download'
*/
download.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::download
* @see app/Http/Controllers/Tenant/NotificationController.php:61
* @route '/notifications/{id}/download'
*/
download.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::download
* @see app/Http/Controllers/Tenant/NotificationController.php:61
* @route '/notifications/{id}/download'
*/
const downloadForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::download
* @see app/Http/Controllers/Tenant/NotificationController.php:61
* @route '/notifications/{id}/download'
*/
downloadForm.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::download
* @see app/Http/Controllers/Tenant/NotificationController.php:61
* @route '/notifications/{id}/download'
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
* @see \App\Http\Controllers\Tenant\NotificationController::destroy
* @see app/Http/Controllers/Tenant/NotificationController.php:48
* @route '/notifications/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/notifications/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\NotificationController::destroy
* @see app/Http/Controllers/Tenant/NotificationController.php:48
* @route '/notifications/{id}'
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
* @see \App\Http\Controllers\Tenant\NotificationController::destroy
* @see app/Http/Controllers/Tenant/NotificationController.php:48
* @route '/notifications/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\NotificationController::destroy
* @see app/Http/Controllers/Tenant/NotificationController.php:48
* @route '/notifications/{id}'
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
* @see \App\Http\Controllers\Tenant\NotificationController::destroy
* @see app/Http/Controllers/Tenant/NotificationController.php:48
* @route '/notifications/{id}'
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

const notifications = {
    readAll: Object.assign(readAll, readAll),
    destroyAll: Object.assign(destroyAll, destroyAll),
    read: Object.assign(read, read),
    download: Object.assign(download, download),
    destroy: Object.assign(destroy, destroy),
}

export default notifications