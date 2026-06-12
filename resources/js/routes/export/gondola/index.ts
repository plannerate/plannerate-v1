import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import section from './section'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::view
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:19
* @route '/export/gondola/{gondola}/view'
*/
export const view = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/export/gondola/{gondola}/view',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::view
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:19
* @route '/export/gondola/{gondola}/view'
*/
view.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return view.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::view
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:19
* @route '/export/gondola/{gondola}/view'
*/
view.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::view
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:19
* @route '/export/gondola/{gondola}/view'
*/
view.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::view
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:19
* @route '/export/gondola/{gondola}/view'
*/
const viewForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: view.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::view
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:19
* @route '/export/gondola/{gondola}/view'
*/
viewForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: view.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController::view
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaPdfPreviewController.php:19
* @route '/export/gondola/{gondola}/view'
*/
viewForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: view.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

view.form = viewForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
export const qrcode = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: qrcode.url(args, options),
    method: 'get',
})

qrcode.definition = {
    methods: ["get","head"],
    url: '/export/gondola/{gondola}/qr-code',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
qrcode.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return qrcode.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
qrcode.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: qrcode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
qrcode.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: qrcode.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
const qrcodeForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: qrcode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
qrcodeForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: qrcode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::qrcode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
qrcodeForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: qrcode.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

qrcode.form = qrcodeForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::report
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
export const report = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: report.url(args, options),
    method: 'get',
})

report.definition = {
    methods: ["get","head"],
    url: '/export/gondola/{gondola}/report',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::report
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
report.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return report.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::report
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
report.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: report.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::report
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
report.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: report.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::report
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
const reportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: report.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::report
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
reportForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: report.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::report
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
reportForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: report.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

report.form = reportForm

const gondola = {
    view: Object.assign(view, view),
    qrcode: Object.assign(qrcode, qrcode),
    section: Object.assign(section, section),
    report: Object.assign(report, report),
}

export default gondola