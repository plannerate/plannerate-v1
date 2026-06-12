import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
export const generateQrCode = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateQrCode.url(args, options),
    method: 'get',
})

generateQrCode.definition = {
    methods: ["get","head"],
    url: '/export/gondola/{gondola}/qr-code',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
generateQrCode.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generateQrCode.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
generateQrCode.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateQrCode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
generateQrCode.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generateQrCode.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
const generateQrCodeForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateQrCode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
generateQrCodeForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateQrCode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:17
* @route '/export/gondola/{gondola}/qr-code'
*/
generateQrCodeForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateQrCode.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generateQrCode.form = generateQrCodeForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateSectionQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:27
* @route '/export/gondola/section/{section}/qr-code'
*/
export const generateSectionQrCode = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateSectionQrCode.url(args, options),
    method: 'get',
})

generateSectionQrCode.definition = {
    methods: ["get","head"],
    url: '/export/gondola/section/{section}/qr-code',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateSectionQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:27
* @route '/export/gondola/section/{section}/qr-code'
*/
generateSectionQrCode.url = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generateSectionQrCode.definition.url
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateSectionQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:27
* @route '/export/gondola/section/{section}/qr-code'
*/
generateSectionQrCode.get = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateSectionQrCode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateSectionQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:27
* @route '/export/gondola/section/{section}/qr-code'
*/
generateSectionQrCode.head = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generateSectionQrCode.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateSectionQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:27
* @route '/export/gondola/section/{section}/qr-code'
*/
const generateSectionQrCodeForm = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateSectionQrCode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateSectionQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:27
* @route '/export/gondola/section/{section}/qr-code'
*/
generateSectionQrCodeForm.get = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateSectionQrCode.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::generateSectionQrCode
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:27
* @route '/export/gondola/section/{section}/qr-code'
*/
generateSectionQrCodeForm.head = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateSectionQrCode.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generateSectionQrCode.form = generateSectionQrCodeForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::exportReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
export const exportReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportReport.url(args, options),
    method: 'get',
})

exportReport.definition = {
    methods: ["get","head"],
    url: '/export/gondola/{gondola}/report',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::exportReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
exportReport.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return exportReport.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::exportReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
exportReport.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::exportReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
exportReport.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportReport.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::exportReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
const exportReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::exportReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
exportReportForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController::exportReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaExportController.php:37
* @route '/export/gondola/{gondola}/report'
*/
exportReportForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportReport.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportReport.form = exportReportForm

const GondolaExportController = { generateQrCode, generateSectionQrCode, exportReport }

export default GondolaExportController