import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:27
* @route '/export/gondola-report/{gondola}/excel'
*/
export const generateExcelReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateExcelReport.url(args, options),
    method: 'get',
})

generateExcelReport.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/excel',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:27
* @route '/export/gondola-report/{gondola}/excel'
*/
generateExcelReport.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generateExcelReport.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:27
* @route '/export/gondola-report/{gondola}/excel'
*/
generateExcelReport.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateExcelReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:27
* @route '/export/gondola-report/{gondola}/excel'
*/
generateExcelReport.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generateExcelReport.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:27
* @route '/export/gondola-report/{gondola}/excel'
*/
const generateExcelReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateExcelReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:27
* @route '/export/gondola-report/{gondola}/excel'
*/
generateExcelReportForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateExcelReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:27
* @route '/export/gondola-report/{gondola}/excel'
*/
generateExcelReportForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateExcelReport.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generateExcelReport.form = generateExcelReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:72
* @route '/export/gondola-report/{gondola}/pdf'
*/
export const generatePdfReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generatePdfReport.url(args, options),
    method: 'get',
})

generatePdfReport.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:72
* @route '/export/gondola-report/{gondola}/pdf'
*/
generatePdfReport.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generatePdfReport.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:72
* @route '/export/gondola-report/{gondola}/pdf'
*/
generatePdfReport.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generatePdfReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:72
* @route '/export/gondola-report/{gondola}/pdf'
*/
generatePdfReport.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generatePdfReport.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:72
* @route '/export/gondola-report/{gondola}/pdf'
*/
const generatePdfReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePdfReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:72
* @route '/export/gondola-report/{gondola}/pdf'
*/
generatePdfReportForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePdfReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:72
* @route '/export/gondola-report/{gondola}/pdf'
*/
generatePdfReportForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePdfReport.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generatePdfReport.form = generatePdfReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:123
* @route '/export/gondola-report/{gondola}/compra'
*/
export const generateCompraReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateCompraReport.url(args, options),
    method: 'get',
})

generateCompraReport.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/compra',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:123
* @route '/export/gondola-report/{gondola}/compra'
*/
generateCompraReport.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generateCompraReport.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:123
* @route '/export/gondola-report/{gondola}/compra'
*/
generateCompraReport.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateCompraReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:123
* @route '/export/gondola-report/{gondola}/compra'
*/
generateCompraReport.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generateCompraReport.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:123
* @route '/export/gondola-report/{gondola}/compra'
*/
const generateCompraReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateCompraReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:123
* @route '/export/gondola-report/{gondola}/compra'
*/
generateCompraReportForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateCompraReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:123
* @route '/export/gondola-report/{gondola}/compra'
*/
generateCompraReportForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateCompraReport.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generateCompraReport.form = generateCompraReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:168
* @route '/export/gondola-report/{gondola}/dimensao'
*/
export const generateDimensaoReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateDimensaoReport.url(args, options),
    method: 'get',
})

generateDimensaoReport.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/dimensao',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:168
* @route '/export/gondola-report/{gondola}/dimensao'
*/
generateDimensaoReport.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generateDimensaoReport.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:168
* @route '/export/gondola-report/{gondola}/dimensao'
*/
generateDimensaoReport.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateDimensaoReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:168
* @route '/export/gondola-report/{gondola}/dimensao'
*/
generateDimensaoReport.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generateDimensaoReport.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:168
* @route '/export/gondola-report/{gondola}/dimensao'
*/
const generateDimensaoReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateDimensaoReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:168
* @route '/export/gondola-report/{gondola}/dimensao'
*/
generateDimensaoReportForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateDimensaoReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:168
* @route '/export/gondola-report/{gondola}/dimensao'
*/
generateDimensaoReportForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateDimensaoReport.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generateDimensaoReport.form = generateDimensaoReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:226
* @route '/export/gondola-report/{gondola}/image'
*/
export const generateImageReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateImageReport.url(args, options),
    method: 'get',
})

generateImageReport.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/image',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:226
* @route '/export/gondola-report/{gondola}/image'
*/
generateImageReport.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generateImageReport.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:226
* @route '/export/gondola-report/{gondola}/image'
*/
generateImageReport.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateImageReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:226
* @route '/export/gondola-report/{gondola}/image'
*/
generateImageReport.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generateImageReport.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:226
* @route '/export/gondola-report/{gondola}/image'
*/
const generateImageReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateImageReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:226
* @route '/export/gondola-report/{gondola}/image'
*/
generateImageReportForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateImageReport.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:226
* @route '/export/gondola-report/{gondola}/image'
*/
generateImageReportForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generateImageReport.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generateImageReport.form = generateImageReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:271
* @route '/export/gondola-report/{gondola}/data'
*/
export const getReportData = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: getReportData.url(args, options),
    method: 'get',
})

getReportData.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:271
* @route '/export/gondola-report/{gondola}/data'
*/
getReportData.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return getReportData.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:271
* @route '/export/gondola-report/{gondola}/data'
*/
getReportData.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: getReportData.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:271
* @route '/export/gondola-report/{gondola}/data'
*/
getReportData.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: getReportData.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:271
* @route '/export/gondola-report/{gondola}/data'
*/
const getReportDataForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: getReportData.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:271
* @route '/export/gondola-report/{gondola}/data'
*/
getReportDataForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: getReportData.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:271
* @route '/export/gondola-report/{gondola}/data'
*/
getReportDataForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: getReportData.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

getReportData.form = getReportDataForm

const GondolaReportController = { generateExcelReport, generatePdfReport, generateCompraReport, generateDimensaoReport, generateImageReport, getReportData }

export default GondolaReportController