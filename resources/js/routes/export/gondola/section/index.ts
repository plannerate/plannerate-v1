import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:28
* @route '/export/gondola/section/{section}/qr-code'
*/
export const qrcode = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: qrcode.url(args, options),
    method: 'get',
})

qrcode.definition = {
    methods: ["get","head"],
    url: '/export/gondola/section/{section}/qr-code',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:28
* @route '/export/gondola/section/{section}/qr-code'
*/
qrcode.url = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { section: args }
    }

    if (Array.isArray(args)) {
        args = {
            section: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        section: args.section,
    }

    return qrcode.definition.url
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:28
* @route '/export/gondola/section/{section}/qr-code'
*/
qrcode.get = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: qrcode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:28
* @route '/export/gondola/section/{section}/qr-code'
*/
qrcode.head = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: qrcode.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:28
* @route '/export/gondola/section/{section}/qr-code'
*/
const qrcodeForm = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: qrcode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:28
* @route '/export/gondola/section/{section}/qr-code'
*/
qrcodeForm.get = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: qrcode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:28
* @route '/export/gondola/section/{section}/qr-code'
*/
qrcodeForm.head = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: qrcode.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

qrcode.form = qrcodeForm

const section = {
    qrcode: Object.assign(qrcode, qrcode),
}

export default section