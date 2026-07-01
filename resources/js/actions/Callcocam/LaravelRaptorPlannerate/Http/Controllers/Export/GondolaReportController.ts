import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
export const generateExcelReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateExcelReport.url(args, options),
    method: 'post',
})

generateExcelReport.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/excel',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
generateExcelReport.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateExcelReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
const generateExcelReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateExcelReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateExcelReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
generateExcelReportForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateExcelReport.url(args, options),
    method: 'post',
})

generateExcelReport.form = generateExcelReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
export const generatePdfReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generatePdfReport.url(args, options),
    method: 'post',
})

generatePdfReport.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/pdf',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
generatePdfReport.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generatePdfReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
const generatePdfReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generatePdfReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePdfReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
generatePdfReportForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generatePdfReport.url(args, options),
    method: 'post',
})

generatePdfReport.form = generatePdfReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
export const generateCompraReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateCompraReport.url(args, options),
    method: 'post',
})

generateCompraReport.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/compra',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
generateCompraReport.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateCompraReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
const generateCompraReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateCompraReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateCompraReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
generateCompraReportForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateCompraReport.url(args, options),
    method: 'post',
})

generateCompraReport.form = generateCompraReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
export const generateDimensaoReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateDimensaoReport.url(args, options),
    method: 'post',
})

generateDimensaoReport.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/dimensao',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
generateDimensaoReport.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateDimensaoReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
const generateDimensaoReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateDimensaoReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateDimensaoReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
generateDimensaoReportForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateDimensaoReport.url(args, options),
    method: 'post',
})

generateDimensaoReport.form = generateDimensaoReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
export const generateImageReport = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateImageReport.url(args, options),
    method: 'post',
})

generateImageReport.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/image',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
generateImageReport.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateImageReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
const generateImageReportForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateImageReport.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generateImageReport
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
generateImageReportForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateImageReport.url(args, options),
    method: 'post',
})

generateImageReport.form = generateImageReportForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
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
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
getReportData.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: getReportData.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
getReportData.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: getReportData.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
const getReportDataForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: getReportData.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
getReportDataForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: getReportData.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::getReportData
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
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

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramRowPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
export const generatePlanogramRowPdf = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generatePlanogramRowPdf.url(args, options),
    method: 'get',
})

generatePlanogramRowPdf.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/planogram-pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramRowPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
generatePlanogramRowPdf.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generatePlanogramRowPdf.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramRowPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
generatePlanogramRowPdf.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generatePlanogramRowPdf.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramRowPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
generatePlanogramRowPdf.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generatePlanogramRowPdf.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramRowPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
const generatePlanogramRowPdfForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePlanogramRowPdf.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramRowPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
generatePlanogramRowPdfForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePlanogramRowPdf.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramRowPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
generatePlanogramRowPdfForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePlanogramRowPdf.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generatePlanogramRowPdf.form = generatePlanogramRowPdfForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramModulesPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
export const generatePlanogramModulesPdf = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generatePlanogramModulesPdf.url(args, options),
    method: 'get',
})

generatePlanogramModulesPdf.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/planogram-modules-pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramModulesPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
generatePlanogramModulesPdf.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return generatePlanogramModulesPdf.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramModulesPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
generatePlanogramModulesPdf.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generatePlanogramModulesPdf.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramModulesPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
generatePlanogramModulesPdf.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generatePlanogramModulesPdf.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramModulesPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
const generatePlanogramModulesPdfForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePlanogramModulesPdf.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramModulesPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
generatePlanogramModulesPdfForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePlanogramModulesPdf.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::generatePlanogramModulesPdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
generatePlanogramModulesPdfForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: generatePlanogramModulesPdf.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

generatePlanogramModulesPdf.form = generatePlanogramModulesPdfForm

const GondolaReportController = { generateExcelReport, generatePdfReport, generateCompraReport, generateDimensaoReport, generateImageReport, getReportData, generatePlanogramRowPdf, generatePlanogramModulesPdf }

export default GondolaReportController