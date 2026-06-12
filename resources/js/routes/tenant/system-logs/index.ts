import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\SystemLogController::index
* @see app/Http/Controllers/Tenant/SystemLogController.php:20
* @route '/system-logs'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/system-logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::index
* @see app/Http/Controllers/Tenant/SystemLogController.php:20
* @route '/system-logs'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::index
* @see app/Http/Controllers/Tenant/SystemLogController.php:20
* @route '/system-logs'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::index
* @see app/Http/Controllers/Tenant/SystemLogController.php:20
* @route '/system-logs'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::index
* @see app/Http/Controllers/Tenant/SystemLogController.php:20
* @route '/system-logs'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::index
* @see app/Http/Controllers/Tenant/SystemLogController.php:20
* @route '/system-logs'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::index
* @see app/Http/Controllers/Tenant/SystemLogController.php:20
* @route '/system-logs'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::download
* @see app/Http/Controllers/Tenant/SystemLogController.php:62
* @route '/system-logs/download'
*/
export const download = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/system-logs/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::download
* @see app/Http/Controllers/Tenant/SystemLogController.php:62
* @route '/system-logs/download'
*/
download.url = (options?: RouteQueryOptions) => {
    return download.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::download
* @see app/Http/Controllers/Tenant/SystemLogController.php:62
* @route '/system-logs/download'
*/
download.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::download
* @see app/Http/Controllers/Tenant/SystemLogController.php:62
* @route '/system-logs/download'
*/
download.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::download
* @see app/Http/Controllers/Tenant/SystemLogController.php:62
* @route '/system-logs/download'
*/
const downloadForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::download
* @see app/Http/Controllers/Tenant/SystemLogController.php:62
* @route '/system-logs/download'
*/
downloadForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::download
* @see app/Http/Controllers/Tenant/SystemLogController.php:62
* @route '/system-logs/download'
*/
downloadForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

download.form = downloadForm

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::clear
* @see app/Http/Controllers/Tenant/SystemLogController.php:105
* @route '/system-logs'
*/
export const clear = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clear.url(options),
    method: 'delete',
})

clear.definition = {
    methods: ["delete"],
    url: '/system-logs',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::clear
* @see app/Http/Controllers/Tenant/SystemLogController.php:105
* @route '/system-logs'
*/
clear.url = (options?: RouteQueryOptions) => {
    return clear.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::clear
* @see app/Http/Controllers/Tenant/SystemLogController.php:105
* @route '/system-logs'
*/
clear.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clear.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::clear
* @see app/Http/Controllers/Tenant/SystemLogController.php:105
* @route '/system-logs'
*/
const clearForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clear.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SystemLogController::clear
* @see app/Http/Controllers/Tenant/SystemLogController.php:105
* @route '/system-logs'
*/
clearForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clear.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

clear.form = clearForm

const systemLogs = {
    index: Object.assign(index, index),
    download: Object.assign(download, download),
    clear: Object.assign(clear, clear),
}

export default systemLogs